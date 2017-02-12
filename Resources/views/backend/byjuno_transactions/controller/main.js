/**
 * $Id: $
 */

//{block name="backend/byjuno_transactions/controller/log"}
Ext.define('Shopware.apps.ByjunoTransactions.controller.Main', {
  /**
    * Extend from the standard ExtJS 4
    * @string
    */
  extend: 'Ext.app.Controller',

  requires: [ 'Shopware.apps.ByjunoTransactions.controller.Log' ],
 

  /**
     * Init-function to create the main-window and assign the paymentStore
     */
  init: function() {
    var me = this;
    me.subApplication.logStore = me.subApplication.getStore('Logs');
    me.subApplication.logStore.load();
    me.subApplication.dataStore = me.subApplication.getStore('Detail');
    me.subApplication.dataStore.load();
    me.mainWindow = me.getView('main.Window').create({
      logStore: me.subApplication.logStore,
    });
    
//    me.dataWindow = me.getView('main.Detailwindow').create({

//    });
    
    this.callParent(arguments);
  }
});
//{/block}