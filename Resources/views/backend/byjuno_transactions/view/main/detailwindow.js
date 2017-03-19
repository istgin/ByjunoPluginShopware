/**
 * $Id: $
 */

//{namespace name=backend/byjuno_transactions/main}

/**
 * todo@all: Documentation
 */
//{block name="backend/byjuno_transactions/view/main/detailwindow"}
Ext.define('Shopware.apps.ByjunoTransactions.view.main.Detailwindow', {
	extend: 'Enlight.app.Window',
    title: '{s name=window_detail_title}Byjuno transactions details{/s}',
    cls: Ext.baseCSSPrefix + 'detail-window',
    alias: 'widget.ByjunoApilogMainDetailWindow',
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
            xtype: 'ByjunoApilogMainDetail',
            itemSelected: me.itemSelected,
        }];

        me.callParent(arguments);
    }
});
//{/block}