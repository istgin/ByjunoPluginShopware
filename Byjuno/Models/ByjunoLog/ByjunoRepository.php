<?php

/**
 * $Id: $
 */

namespace Shopware\CustomModels\ByjunoLog;

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
      /*
    $apiLog = new \Shopware\CustomModels\ByjunoLog\ByjunoLog();

    //special transaction status handling
    if ($response instanceof \Payone_TransactionStatus_Request_Interface)
    {
      $apiLog->setRequest(get_class($response));
    }
    else
    {
      $apiLog->setRequest($request->getRequest());
      $apiLog->setResponse($response->getStatus());
      if ($request->getMode() == 'live')
      {
        $apiLog->setLiveMode(true);
      }
      else
      {
        $apiLog->setLiveMode(false);
      }
      $apiLog->setMerchantId($request->getMid());
      $apiLog->setPortalId($request->getPortalid());
      $apiLog->setCreationDate(date('Y-m-d\TH:i:sP'));
      $apiLog->setRequestDetails($request->__toString());
      $apiLog->setResponseDetails($response->__toString());
    }

    Shopware()->Models()->persist($apiLog);
    Shopware()->Models()->flush();
      */
  }

  /**
   * @param Payone_Api_Request_Interface $request
   * @param Exception
   * @return boolean
   */
  public function saveException(Payone_Api_Request_Interface $request, Exception $ex)
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
            ->from('Shopware\CustomModels\ByjunoLog\ByjunoLog', 'm');
    return $builder;
  }

}