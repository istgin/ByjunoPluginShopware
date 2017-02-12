/**
 * $Id: $
 */

/**
 * Shopware - Log model
 *
 * This model represents a single log of s_core_log.
 */
//{block name="backend/byjuno_transactions/model/grid2cols"}
Ext.define('Shopware.apps.ByjunoTransactions.model.Grid2cols', {
  /**
    * Extends the standard ExtJS 4
    * @string
    */
  extend: 'Ext.data.Model',
  /**
    * The fields used for this model
    * @array
    */
  fields: [
  //{block name="backend/byjuno_transactions/model/grid2cols/fields"}{/block}
  'name',
  'value'
  ]
  
});
//{/block}