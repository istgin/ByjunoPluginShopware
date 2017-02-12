/**
 * $Id: $
 */

/**
 * Shopware - Users store
 *
 * This store contains all users.
 */
//{block name="backend/byjuno_transactions/store/users"}
Ext.define('Shopware.apps.ByjunoTransactions.store.Users', {

    /**
    * Extend for the standard ExtJS 4
    * @string
    */
    extend: 'Shopware.apps.Base.store.User',
    /**
    * Amount of data loaded at once
    * @integer
    */
    pageSize: 2000
});
//{/block}