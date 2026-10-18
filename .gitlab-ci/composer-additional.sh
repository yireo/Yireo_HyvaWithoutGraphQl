for HYVA_PACKAGE in magento2-default-theme-csp magento2-theme-module magento2-base-layout-reset magento2-email-module magento2-graphql-tokens magento2-graphql-view-model magento2-order-cancellation-webapi magento2-mollie-theme-bundle magento2-compat-module-fallback; do
  composer config repositories.hyva-${HYVA_PACKAGE} vcs https://github.com/hyva-themes/${HYVA_PACKAGE}.git
done
