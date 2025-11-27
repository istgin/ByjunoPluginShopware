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
    me.subApplication.logStore = me.subApplication.getStore('Shopware.apps.CembrapayTransactions.store.Logs');
    me.subApplication.logStore.load();
    me.subApplication.dataStore = me.subApplication.getStore('Shopware.apps.CembrapayTransactions.store.Detail');
    me.subApplication.dataStore.load();
    me.mainWindow = me.getView('Shopware.apps.CembrapayTransactions.view.main.Window').create({
      logStore: me.subApplication.logStore,
    });
    
    this.callParent(arguments);
  }
});
//{/block}