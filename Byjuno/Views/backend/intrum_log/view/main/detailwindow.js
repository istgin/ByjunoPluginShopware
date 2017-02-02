/**
 * $Id: $
 */

//{namespace name=backend/byjuno_log/main}

/**
 * todo@all: Documentation
 */
//{block name="backend/byjuno_log/view/main/detailwindow"}
Ext.define('Shopware.apps.ByjunoLog.view.main.Detailwindow', {
	extend: 'Enlight.app.Window',
    title: '{s name=window_detail_title}API-Log Details{/s}',
    cls: Ext.baseCSSPrefix + 'detail-window',
    alias: 'widget.moptPayoneApilogMainDetailWindow',
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
            xtype: 'moptPayoneApilogMainDetail',
            itemSelected: me.itemSelected,
        }];

        me.callParent(arguments);
    }
});
//{/block}