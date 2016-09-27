
Ext.define('Shopware.apps.PilipayWarehouses.view.list.Window', {
    extend: 'Shopware.window.Listing',
    alias: 'widget.product-list-window',
    height: 450,
    title : '{s name=window_title_warehouse}Warehouses listing{/s}',

    configure: function() {
        return {
            listingGrid: 'Shopware.apps.PilipayWarehouses.view.list.Pilipawarehouses',
            listingStore: 'Shopware.apps.PilipayWarehouses.store.Pilipawarehouses'
        };
    }
});