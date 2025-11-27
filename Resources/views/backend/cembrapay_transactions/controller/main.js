/**
 * $Id: $
 */

//{block name="backend/cembrapay_transactions/controller/log"}
Ext.define('Shopware.apps.CembrapayTransactions.controller.Main', {
  /**
    * Extend from the standard ExtJS 4
    * @string
    */
  extend: 'Ext.app.Controller',

  requires: [ 'Shopware.apps.CembrapayTransactions.controller.Log' ],
 

  /**
     * Init-function to create the main-window and assign the paymentStore
     */
  init: function() {
    var me = this;
    me.subApplication.logStoreCembrapay = me.subApplication.getStore('Shopware.apps.CembrapayTransactions.store.Logs');
    me.subApplication.logStoreCembrapay.load();
    me.subApplication.dataStoreCembrapay = me.subApplication.getStore('Shopware.apps.CembrapayTransactions.store.Detail');
    me.subApplication.dataStoreCembrapay.load();
    me.mainWindow = me.getView('Shopware.apps.CembrapayTransactions.view.main.Window').create({
      logStoreCembrapay: me.subApplication.logStoreCembrapay,
    });

    this.callParent(arguments);
  }
});
//{/block}