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
 * @ORM\Table(name="s_plugin_byjuno_documents")
 */
//$doucmentId, $amount, $orderAmount, $orderCurrency, $orderId, $customerId, $date
class ByjunoDocuments extends ModelEntity
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
     * @ORM\Column(name="document_id", type="string", length=100, precision=0, scale=0, nullable=false, unique=false)
     */
    private $documentId;

    /**
     * @ORM\Column(name="amount", type="decimal", precision=0, scale=0, nullable=false, unique=false)
     */
    private $amount;

    /**
     * @ORM\Column(name="order_amount", type="decimal", precision=0, scale=0, nullable=false, unique=false)
     */
    private $orderAmount;

    /**
     * @ORM\Column(name="order_currency", type="string", precision=0, scale=0, nullable=false, unique=false)
     */
    private $orderCurrency;

    /**
     * @ORM\Column(name="order_id", type="string", precision=0, scale=0, nullable=false, unique=false)
     */
    private $orderId;

    /**
     * @ORM\Column(name="customer_id", type="string", precision=0, scale=0, nullable=false, unique=false)
     */
    private $customerId;

    /**
     * @return mixed
     */
    public function getCustomerId()
    {
        return $this->customerId;
    }

    /**
     * @param mixed $customerId
     */
    public function setCustomerId($customerId)
    {
        $this->customerId = $customerId;
    }

    /**
     * @ORM\Column(name="date", type="string", precision=0, scale=0, nullable=false, unique=false)
     */
    private $date;

    /**
     * @ORM\Column(name="document_type", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    private $document_type;

    /**
     * @ORM\Column(name="document_sent", type="boolean", precision=0, scale=0, nullable=false, unique=false)
     */
    private $document_sent;

    /**
     * @ORM\Column(name="document_try_time", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    private $document_try_time;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getDocumentId()
    {
        return $this->documentId;
    }

    /**
     * @param mixed $documentId
     */
    public function setDocumentId($documentId)
    {
        $this->documentId = $documentId;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param mixed $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return mixed
     */
    public function getOrderAmount()
    {
        return $this->orderAmount;
    }

    /**
     * @param mixed $orderAmount
     */
    public function setOrderAmount($orderAmount)
    {
        $this->orderAmount = $orderAmount;
    }

    /**
     * @return mixed
     */
    public function getOrderCurrency()
    {
        return $this->orderCurrency;
    }

    /**
     * @param mixed $orderCurrency
     */
    public function setOrderCurrency($orderCurrency)
    {
        $this->orderCurrency = $orderCurrency;
    }

    /**
     * @return mixed
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @param mixed $orderId
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * @return mixed
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param mixed $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * @return mixed
     */
    public function getDocumentType()
    {
        return $this->document_type;
    }

    /**
     * @param mixed $document_type
     */
    public function setDocumentType($document_type)
    {
        $this->document_type = $document_type;
    }

    /**
     * @return mixed
     */
    public function getDocumentSent()
    {
        return $this->document_sent;
    }

    /**
     * @param mixed $document_sent
     */
    public function setDocumentSent($document_sent)
    {
        $this->document_sent = $document_sent;
    }

    /**
     * @return mixed
     */
    public function getDocumentTryTime()
    {
        return $this->document_try_time;
    }

    /**
     * @param mixed $document_try_time
     */
    public function setDocumentTryTime($document_try_time)
    {
        $this->document_try_time = $document_try_time;
    }

    public function __construct()
    {
        $this->apiLogs = new \Doctrine\Common\Collections\ArrayCollection();
    }


}