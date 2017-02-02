/**
 * $Id: $
 */

/**
 * Shopware - Users store
 *
 * This store contains all users.
 */
//{block name="backend/byjuno_log/store/users"}
Ext.define('Shopware.apps.ByjunoLog.store.Users', {

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