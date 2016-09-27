
Ext.define('Shopware.apps.PilipayWarehouses.store.Pilipawarehouses', {
    extend:'Shopware.store.Listing',

    configure: function() {
        return {
            controller: 'PilipayWarehouses'
        };
    },
    model: 'Shopware.apps.PilipayWarehouses.model.Pilipawarehouses'
});