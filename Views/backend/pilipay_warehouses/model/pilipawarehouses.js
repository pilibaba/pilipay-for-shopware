
Ext.define('Shopware.apps.PilipayWarehouses.model.Pilipawarehouses', {
    extend: 'Shopware.data.Model',

    configure: function() {
        return {
            controller: 'PilipayWarehouses',
            detail: 'Shopware.apps.PilipayWarehouses.view.detail.Pilipawarehouses'
        };
    },


    fields: [
        { name : 'id', type: 'int', useNull: true },
        { name : 'name', type: 'string' },
        { name : 'receiverFirstName', type: 'string' },
        { name : 'receiverLastName', type: 'string' },
        { name : 'street', type: 'string' },
        { name : 'addressLine1', type: 'string' },
        { name : 'addressLine2', type: 'string' },
        { name : 'zipCode', type: 'string' },
        { name : 'company', type: 'string' },
        { name : 'city', type: 'string' },
        { name : 'state', type: 'string' },
        { name : 'country', type: 'string' },
        { name : 'countryIsoCode', type: 'string' },
        { name : 'active', type: 'boolean' },
    ]
});

