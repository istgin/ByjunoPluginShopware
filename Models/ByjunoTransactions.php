<?php

/**
 * $Id: $
 */

namespace ByjunoPayments\Models;

use Shopware\Components\Model\ModelEntity,
    Doctrine\ORM\Mapping AS ORM,
    Symfony\Component\Validator\Constraints as Assert,
    Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass="ByjunoRepository")
 * @ORM\Table(name="s_plugin_byjuno_transactions")
 */
class ByjunoTransactions extends ModelEntity
{

  /**
   * @var integer $id
   *
   * @ORM\Column(name="id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @ORM\Column(name="requestid", type="string", length=100, precision=0, scale=0, nullable=false, unique=false)
   */
  private $requestid;

  /**
   * @ORM\Column(name="requesttype", type="string", precision=0, scale=0, nullable=false, unique=false)
   */
  private $requesttype;

  /**
   * @ORM\Column(name="firstname", type="string", precision=0, scale=0, nullable=false, unique=false)
   */
  private $firstname;

  /**
   * @ORM\Column(name="lastname", type="string", precision=0, scale=0, nullable=false, unique=false)
   */
  private $lastname;

  /**
   * @ORM\Column(name="ip", type="string", precision=0, scale=0, nullable=false, unique=false)
   */
  private $ip;

  /**
   * @ORM\Column(name="status", type="string", precision=0, scale=0, nullable=false, unique=false)
   */
  private $status;

  /**
   * @ORM\Column(name="datecolumn", type="datetime", precision=0, scale=0, nullable=false, unique=false)
   */
  private $datecolumn;

    /**
     * @ORM\Column(name="xml_request", type="text", precision=0, scale=0, nullable=false, unique=false)
     */
    private $xml_request;

    /**
     * @ORM\Column(name="xml_responce", type="text", precision=0, scale=0, nullable=false, unique=false)
     */
    private $xml_responce;
  /**
   * @var \Doctrine\Common\Collections\ArrayCollection
   */
  private $apiLogs;

  public function __construct()
  {
    $this->apiLogs = new \Doctrine\Common\Collections\ArrayCollection();
  }

  /**
   * add apiLog to collection
   *
   * @param \Shopware\CustomModels\ByjunoTransactions\ByjunoLog $apiLog
   */
  public function addApiLog(\Shopware\CustomModels\ByjunoTransactions\ByjunoLog $apiLog)
  {
    $this->apiLogs[] = $apiLog;
  }

  /**
   * Set apiLogs collection
   *
   * @param $apiLogs
   *
   * @return \Doctrine\Common\Collections\Collection
   */
  public function setApiLogs($apiLogs)
  {
    $this->apiLogs = $apiLogs;
    return $this;
  }

  /**
   * Get apiLogs collection
   *
   * @return \Doctrine\Common\Collections\Collection
   */
  public function getApiLogs()
  {
    return $this->apiLogs;
  }

  public function getId()
  {
    return $this->id;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

    /**
     * @param mixed $firstname
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;
    }

    /**
     * @return mixed
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * @param mixed $ip
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    /**
     * @return mixed
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param mixed $lastname
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;
    }

    /**
     * @return mixed
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * @param mixed $requestid
     */
    public function setRequestid($requestid)
    {
        $this->requestid = $requestid;
    }

    /**
     * @return mixed
     */
    public function getRequestid()
    {
        return $this->requestid;
    }

    /**
     * @param mixed $requesttype
     */
    public function setRequesttype($requesttype)
    {
        $this->requesttype = $requesttype;
    }

    /**
     * @return mixed
     */
    public function getRequesttype()
    {
        return $this->requesttype;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $xml_request
     */
    public function setXmlRequest($xml_request)
    {
        $this->xml_request = $xml_request;
    }

    /**
     * @return mixed
     */
    public function getXmlRequest()
    {
        return $this->xml_request;
    }

    /**
     * @param mixed $xml_responce
     */
    public function setXmlResponce($xml_responce)
    {
        $this->xml_responce = $xml_responce;
    }

    /**
     * @return mixed
     */
    public function getXmlResponce()
    {
        return $this->xml_responce;
    }

    /**
     * @param mixed $datecolumn
     */
    public function setDatecolumn($datecolumn)
    {
        $this->datecolumn = $datecolumn;
    }

    /**
     * @return mixed
     */
    public function getDatecolumn()
    {
        return $this->datecolumn;
    }


}