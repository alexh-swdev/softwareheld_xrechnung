<?php

/**
 * Transfer object
 */
class SoftwareHeld_Xrechnung_Model_Result
{
    private bool $sucess = false;
    private string $message = "";
    /** @var Mage_Sales_Model_Order_Invoice[] */
    private array $invoices = [];
    /** @var Mage_Sales_Model_Order_Creditmemo[] */
    private array $creditMemos = [];
    private string $xmlInvoice = "";

    public function isSucess(): bool
    {
        return $this->sucess;
    }

    public function setSucess(bool $sucess): static
    {
        $this->sucess = $sucess;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMessages(): array
    {
        if (empty($this->message)) {
            return [];
        }

        return explode(PHP_EOL, trim($this->message));
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function addMessage(string $message): static
    {
        $this->message .= PHP_EOL . $message;
        return $this;
    }

    public function getInvoices(): array
    {
        return $this->invoices;
    }

    public function addInvoice(Mage_Sales_Model_Order_Invoice $invoice): static
    {
        $this->invoices[] = $invoice;
        return $this;
    }

    public function getCreditMemos(): array
    {
        return $this->creditMemos;
    }

    public function addCreditMemo(Mage_Sales_Model_Order_Creditmemo $creditMemo): static
    {
        $this->creditMemos[] = $creditMemo;
        return $this;
    }

    public function getXmlInvoice(): string
    {
        return $this->xmlInvoice;
    }

    public function setXmlInvoice(string $xmlInvoice): static
    {
        $this->xmlInvoice = $xmlInvoice;
        return $this;
    }
}