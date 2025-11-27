/**
 * $Id: $
 */

//{namespace name=backend/cembrapay_transactions/main}
/**
 * Shopware UI - Log view list
 *
 * This grid contains all logs and its information.
 */
//{block name="backend/cembrapay_transactions/view/log/list"}
Ext.define('Shopware.apps.CembrapayTransactions.view.log.List', {
  /**
   * Extend from the standard ExtJS 4
   * @string
   */
  extend: 'Ext.grid.Panel',
  border: 0,
  ui: 'shopware-ui',
  /**
   * Alias name for the view. Could be used to get an instance
   * of the view through Ext.widget('moptPayoneApilogMainList')
   * @string
   */
  alias: 'widget.CembrapayTransactionsApilogMainList',
  /**
   * The window uses a border layout, so we need to set
   * a region for the grid panel
   * @string
   */
  region: 'center',
  /**
   * The view needs to be scrollable
   * @string
   */
  autoScroll: true,
  /**
   * Sets up the ui component
   * @return void
   */
  initComponent: function() {
    var me = this;
    me.registerEvents();
    me.selModel = me.createSelectionModel();
    me.store = me.logStoreCembrapay;

    me.columns = me.getColumns();
    //me.toolbar = me.getToolbar(me);

    me.dockedItems = [];
    me.dockedItems.push(me.toolbar);
    me.dockedItems.push({
      dock: 'bottom',
      xtype: 'pagingtoolbar',
      displayInfo: true,
      store: me.store,
      width: '50%'
    });



    me.callParent(arguments);
  },
  /**
   *  Creates the columns
   *
   *  @return array columns Contains all columns
   */
  getColumns: function() {
    var me = this;
    var columns = [{
        header: 'ID',
        dataIndex: 'id',
        flex: 1
      }, {
        header: 'Request Id',
        dataIndex: 'requestid',
        flex: 1
      }, {
        header: 'Request Type',
        dataIndex: 'requesttype',
        flex: 1
      }, {
        header: 'First Name',
        dataIndex: 'firstname',
        flex: 1
      }, {
        header: 'Last name',
        dataIndex: 'lastname',
        flex: 1
      }, {
        header: 'IP',
        dataIndex: 'ip',
        flex: 1
      }, {
        header: 'Status',
        dataIndex: 'status',
        flex: 1
      }, {
        header: 'Date',
        dataIndex: 'datecolumn',
        xtype: 'datecolumn',
        flex: 1,
        renderer: me.renderDate
      }
    ];
    return columns;
  },
  /**
   * Renders the date
   *
   * @param value
   * @return [date] value Contains the date
   */
  renderDate: function(value) {
    return Ext.util.Format.date(value) + ' ' + Ext.util.Format.date(value, 'H:i:s');
  },
  /**
   * Defines additional events which will be
   * fired from the component
   *
   * @return void
   */
  registerEvents: function() {
    this.addEvents('selectColumn');
  },
  createSelectionModel: function() {
    var me = this;

    return Ext.create('Ext.selection.RowModel', {
      listeners: {
        selectionchange: function(view, selected) {
          if(selected[0])
          {
            me.detail = Ext.create('Shopware.apps.CembrapayTransactions.view.main.Detailwindow', {
              itemSelected: selected[0].data.id
            }).show();
          }
        }
      }
    });
  },
  getToolbar: function(me)
  {

    var items = [
      '-',
      , {
        xtype: 'textfield',
        name: 'searchApi',
        id: 'searchFieldApi',
        dock: 'top',
        fieldLabel: 'Freitext'
      }, {
        xtype: 'button',
        name: 'searchbtnapi',
        text: 'Suchen',
        id: 'searchBtnApi',
        width: '50px',
        dock: 'top',
        handler: function(btn, event) {
          var value = Ext.getCmp('searchFieldApi').getValue();
          var stori = me.store;

          data = stori.load({
            action: 'search',
            pageSize: 20,
            filters: [{
                property: 'search',
                value: value
              }],
          });
        }
      },
      '-',
      {
        xtype: 'button',
        name: 'resetApiBtn',
        text: 'Suche zurücksetzen',
        id: 'resetApiBtn',
        dock: 'top',
        handler: function(btn, event) {
          var stori = me.store;
          Ext.getCmp('searchFieldApi').setValue('');
          data = stori.load({
            action: 'search',
            pageSize: 20,
          });
        }
      }
    ];
    return Ext.create('Ext.toolbar.Toolbar', {
      dock: 'top',
      ui: 'shopware-ui',
      items: items
    });
  }
});
//{/block}