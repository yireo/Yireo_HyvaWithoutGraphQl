# Hyvä its recurring setup bootstraps a separate ObjectManager from app/etc/config.php, which is missing in the integration test sandbox
case "${GITHUB_JOB:-$CI_JOB_NAME}" in integration-tests*)
  test -f app/etc/config.php || php bin/magento module:enable --all ;;
esac
