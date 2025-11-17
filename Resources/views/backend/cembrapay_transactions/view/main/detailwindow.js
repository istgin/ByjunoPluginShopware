/**
 * $Id: $
 */

//{namespace name=backend/cembrapay_transactions/main}

/**
 * todo@all: Documentation
 */
//{block name="backend/cembrapay_transactions/view/main/detailwindow"}
Ext.define('Shopware.apps.CembrapayTransactions.view.main.Detailwindow', {
	extend: 'Enlight.app.Window',
    title: '{s name="window_detail_title"}CembraPay transactions details{/s}',
    cls: Ext.baseCSSPrefix + 'detail-window',
    alias: 'widget.CembrapayApilogMainDetailWindow',
    border: false,
    autoShow: true,
    layout: 'border',
    height: '90%',
    width: 800,

    stateful: true,
    stateId:'shopware-detail-window',

    /**
     * Initializes the component and builds up the main interface
     *
     * @return void
     */
    initComponent: function() {
        var me = this;
        me.title = 'API-Log Details zu ID ' + me.itemSelected;
        me.items = [{
            xtype: 'CembrapayApilogMainDetail',
            itemSelected: me.itemSelected,
        }];

        me.callParent(arguments);
    }
});
//{/block}