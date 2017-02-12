<?php

/**
 * $Id: $
 */

namespace ByjunoPayments\Models;

use Shopware\Components\Model\ModelRepository;

/**
 * Transaction Log Repository
 */
class ByjunoRepository extends ModelRepository
{

  const KEY = 'p1_shopware_api';

  /**
   * @return string
   */
  public function getKey()
  {
    return self::KEY;
  }

  public function save($request, $response)
  {

  }

  /**
   * Helper function to create the query builder
   * @return \Doctrine\ORM\QueryBuilder
   */
  public function getApiLogQueryBuilder()
  {
    $builder = $this->getEntityManager()->createQueryBuilder();
    $builder->select(array('m.id', 'm.requestid', 'm.requesttype', 'm.firstname', 'm.lastname',
        'm.ip', 'm.status', 'm.datecolumn'))
            ->from('ByjunoPayments\Models\ByjunoTransactions', 'm');
    return $builder;
  }

}