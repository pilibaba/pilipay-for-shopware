
Ext.define('Shopware.apps.PilipayWarehouses', {
    extend: 'Enlight.app.SubApplication',

    name:'Shopware.apps.PilipayWarehouses',

    loadPath: '{url action=load}',
    bulkLoad: true,

    controllers: [ 'Main' ],

    views: [
        'list.Window',
        'list.Pilipawarehouses',

        'detail.Pilipawarehouses',
        'detail.Window'
    ],

    models: [ 'Pilipawarehouses' ],
    stores: [ 'Pilipawarehouses' ],

    launch: function() {
        return this.getController('Main').mainWindow;
    }
});