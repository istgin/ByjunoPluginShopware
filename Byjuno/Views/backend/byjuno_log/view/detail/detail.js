/**
 * $Id: $
 */

//{namespace name=backend/byjuno_log/main}

/**
 * Shopware UI - Log view list
 *
 * This grid contains all logs and its information.
 */
//{block name="backend/byjuno_log/view/detail/detail"}

Ext.define('ExternalInfoWindow', {
    extend: 'Ext.data.Connection',
    singleton: true,
    loading: false,
    page: false,
    language: false,
    constructor : function(config){
        this.callParent([config]);
    }
});

Ext.define('Shopware.apps.ByjunoLog.view.detail.Detail', {
  /**
   * Extend from the standard ExtJS 4
   * @string
   */
  extend: 'Ext.panel.Panel',
  layout: {
    type: 'hbox',
    align: 'stretch'
  },
  
  width: 800,
  
  border: 0,
  ui: 'shopware-ui',
  /**
   * Alias name for the view. Could be used to get an instance
   * of the view through Ext.widget('moptPayoneApilogMainDetail')
   * @string
   */
  alias: 'widget.ByjunoApilogMainDetail',
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

    me.items = [
        {
            id: 'request_xml',
            autoScroll: true,
            title: 'Request',
            width: '50%',
            html: '',
            flex: 1,
            listeners: {
                boxready:function (e) {
                    url = 'ByjunoLog/getGridData?id='+me.itemSelected+'&type=request';
                    Ext.Ajax.request({
                        url:'{url controller="ByjunoLog" action="getGridData"}',
                        method: 'GET',
                        params: {
                            id: me.itemSelected,
                            type: 'request'
                        },
                        success:function (r) {
                            e.update(r.responseText);
                        }
                    });
                }
            }
        },
        {
            id: 'response_xml',
            autoScroll: true,
            title: 'Request',
            width: '50%',
            html: '',
            flex: 1,
            listeners: {
                boxready:function (e) {
                    url = 'ByjunoLog/getGridData?id='+me.itemSelected+'&type=response';
                    Ext.Ajax.request({
                        url:'{url controller="ByjunoLog" action="getGridData"}',
                        method: 'GET',
                        params: {
                            id: me.itemSelected,
                            type: 'response'
                        },
                        success:function (r) {
                            e.update(r.responseText);
                        }
                    });
                }
            }
        }
    ];


//    me.items = [
//      {
//        xtype: 'tablepanel',
//        fieldLabel: 'Start date'
//      },
//      {
//        xtype: 'tablepanel',
//        fieldLabel: 'End date'
//      }
//    ];

    me.callParent(arguments);
  },
});
//{/block}