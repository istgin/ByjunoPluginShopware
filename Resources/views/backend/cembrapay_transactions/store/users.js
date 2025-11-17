/**
 * $Id: $
 */

/**
 * Shopware - Users store
 *
 * This store contains all users.
 */
//{block name="backend/cembrapay_transactions/store/users"}
Ext.define('Shopware.apps.CembrapayTransactions.store.Users', {

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