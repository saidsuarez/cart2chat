(function () {
    var wc = window.wc || {};
    var registry = wc.wcBlocksRegistry;
    var settingsApi = wc.wcSettings;
    var wpElement = window.wp && window.wp.element ? window.wp.element : null;
    var htmlEntities = window.wp && window.wp.htmlEntities ? window.wp.htmlEntities : null;
    var wpI18n = window.wp && window.wp.i18n ? window.wp.i18n : null;

    if (!registry || !registry.registerPaymentMethod || !settingsApi || !wpElement || !htmlEntities || !wpI18n) {
        return;
    }

    var createElement = wpElement.createElement;
    var decodeEntities = htmlEntities.decodeEntities;
    var __ = wpI18n.__;
    var settings = settingsApi.getSetting('pv_whatsapp_data', {});
    var title = decodeEntities(settings.title || __('Order via WhatsApp', 'cart2chat'));
    var description = decodeEntities(settings.description || __('We will contact you via WhatsApp to confirm payment and production.', 'cart2chat'));

    var Content = function () {
        return createElement('div', { style: { lineHeight: '1.45' } }, description);
    };

    registry.registerPaymentMethod({
        name: 'pv_whatsapp',
        label: createElement('span', null, title),
        content: createElement(Content, null),
        edit: createElement(Content, null),
        canMakePayment: function () {
            return true;
        },
        ariaLabel: title,
        supports: settings.supports || {
            features: ['products']
        }
    });
})();
