<?php
declare(strict_types=1);

namespace Yireo\HyvaWithoutGraphQl\Plugin\TemplateEngine;

use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\FileSystem as ViewFileSystem;
use Magento\Framework\View\TemplateEngine\Php;
use Yireo\HyvaWithoutGraphQl\Config\Config;

/**
 * Replaces or extends Hyvä templates that contain a GraphQL call.
 *
 * Templates listed in `replaceTemplates` are swapped for an alternative template. Templates listed
 * in `appendTemplates` keep their own output, but an additional template is rendered right after it
 * using the very same block instance.
 */
class SwapGraphQlTemplates
{
    private const GRAPHQL_MARKER = 'graphql';

    /**
     * @param Config $config
     * @param ViewFileSystem $viewFileSystem
     * @param array<string, array{match: string, template: string}> $replaceTemplates
     * @param array<string, array{match: string, template: string}> $appendTemplates
     */
    public function __construct(
        private readonly Config $config,
        private readonly ViewFileSystem $viewFileSystem,
        private readonly array $replaceTemplates = [],
        private readonly array $appendTemplates = []
    ) {
    }

    /**
     * @param Php $subject
     * @param BlockInterface $block
     * @param string $fileName
     * @param array<string, mixed> $dictionary
     * @return array{0: BlockInterface, 1: string, 2: array<string, mixed>}
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeRender(
        Php $subject,
        BlockInterface $block,
        $fileName,
        array $dictionary = []
    ): array {
        if (!$this->config->isEnabled()) {
            return [$block, $fileName, $dictionary];
        }

        $template = $this->matchTemplate($this->replaceTemplates, (string)$fileName);
        if ($template === null) {
            return [$block, $fileName, $dictionary];
        }

        $replacementFileName = $this->viewFileSystem->getTemplateFileName($template);
        if (!$replacementFileName) {
            return [$block, $fileName, $dictionary];
        }

        return [$block, $replacementFileName, $dictionary];
    }

    /**
     * @param Php $subject
     * @param string $result
     * @param BlockInterface $block
     * @param string $fileName
     * @param array<string, mixed> $dictionary
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterRender(
        Php $subject,
        $result,
        BlockInterface $block,
        $fileName,
        array $dictionary = []
    ): string {
        $result = (string)$result;
        if (!$block instanceof Template || !$this->config->isEnabled()) {
            return $result;
        }

        if (stripos($result, self::GRAPHQL_MARKER) === false) {
            return $result;
        }

        $template = $this->matchTemplate($this->appendTemplates, (string)$fileName);
        if ($template === null) {
            return $result;
        }

        $additionalFileName = $block->getTemplateFile($template);
        if (!$additionalFileName) {
            return $result;
        }

        return $result . $block->fetchView($additionalFileName);
    }

    /**
     * @param array<string, array{match: string, template: string}> $templates
     * @param string $fileName
     * @return string|null
     */
    private function matchTemplate(array $templates, string $fileName): ?string
    {
        if ($fileName === '') {
            return null;
        }

        $fileName = str_replace('\\', '/', $fileName);
        foreach ($templates as $candidate) {
            $match = (string)($candidate['match'] ?? '');
            $template = (string)($candidate['template'] ?? '');
            if ($match === '' || $template === '') {
                continue;
            }

            if (str_ends_with($fileName, $match)) {
                return $template;
            }
        }

        return null;
    }
}
