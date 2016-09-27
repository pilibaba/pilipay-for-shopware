

Ext.define('Shopware.apps.PilipayWarehouses.view.list.Pilipawarehouses', {
    extend: 'Shopware.grid.Panel',
    alias:  'widget.product-listing-grid',
    region: 'center',

    configure: function() {
        return {
            detailWindow: 'Shopware.apps.PilipayWarehouses.view.detail.Window'
        };
    }
});
