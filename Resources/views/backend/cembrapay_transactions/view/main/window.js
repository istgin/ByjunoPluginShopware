/**
 * $Id: $
 */

//{namespace name=backend/cembrapay_transactions/main}

/**
 * todo@all: Documentation
 */
//{block name="backend/cembrapay_transactions/view/main/window"}
Ext.define('Shopware.apps.CembrapayTransactions.view.main.Window', {
  extend: 'Enlight.app.Window',
  title: '{s name="window_title"}CembraPay transactions log{/s}',
  cls: Ext.baseCSSPrefix + 'log-window',
  alias: 'widget.log-main-window-api-cembrapay',
  border: false,
  autoShow: true,
  height: 550,
  layout: 'border',
  width: 1000,
  stateful: true,
  stateId: 'shopware-log-window',
  /**
   * Initializes the component and builds up the main interface
   *
   * @return void
   */
  initComponent: function() {
    var me = this;
    me.items = [
      {
        xtype: 'CembrapayTransactionsApilogMainList',
        logStoreCembrapay: me.logStoreCembrapay
      },
    ];

    me.callParent(arguments);
  }
});
//{/block}