<?php

namespace Omnipay\Realex\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Realex 3D Secure enrolment request
 */
class EnrolmentRequest extends RemoteAbstractRequest
{

    /**
     * Get the XML registration string to be sent to the gateway
     *
     * @return string
     */
    public function getData()
    {
        $this->validate('amount', 'currency', 'transactionId');

        // Create the hash
        $timestamp = strftime("%Y%m%d%H%M%S");
        $merchantId = $this->getMerchantId();
        $orderId = $this->getTransactionId();
        $amount = $this->getAmountInteger();
        $currency = $this->getCurrency();
        $cardNumber = $this->getCard()->getNumber();
        $secret = $this->getSecret();
        $tmp = "$timestamp.$merchantId.$orderId.$amount.$currency.$cardNumber";
        $sha1hash = sha1($tmp);
        $tmp2 = "$sha1hash.$secret";
        $sha1hash = sha1($tmp2);

        $domTree = new \DOMDocument('1.0', 'UTF-8');

        // root element
        $root = $domTree->createElement('request');
        $root->setAttribute('type', '3ds-verifyenrolled');
        $root->setAttribute('timestamp', $timestamp);
        $root = $domTree->appendChild($root);

        // merchant ID
        $merchantEl = $domTree->createElement('merchantid');
        $merchantEl->appendChild($domTree->createTextNode($merchantId));
        $root->appendChild($merchantEl);

        // account
        $merchantEl = $domTree->createElement('account');
        $merchantEl->appendChild($domTree->createTextNode($this->getAccount()));
        $root->appendChild($merchantEl);

        // order ID
        $merchantEl = $domTree->createElement('orderid');
        $merchantEl->appendChild($domTree->createTextNode($orderId));
        $root->appendChild($merchantEl);

        // amount
        $amountEl = $domTree->createElement('amount');
        $amountEl->appendChild($domTree->createTextNode($amount));
        $amountEl->setAttribute('currency', $this->getCurrency());
        $root->appendChild($amountEl);

        /**
         * @var \Omnipay\Common\CreditCard $card
         */
        $card = $this->getCard();

        // Card details
        $cardEl = $domTree->createElement('card');

        $cardNumberEl = $domTree->createElement('number');
        $cardNumberEl->appendChild($domTree->createTextNode($card->getNumber()));
        $cardEl->appendChild($cardNumberEl);

        $expiryEl = $domTree->createElement('expdate'); // mmyy
        $expiryEl->appendChild($domTree->createTextNode($card->getExpiryDate("my")));
        $cardEl->appendChild($expiryEl);

        $cardTypeEl = $domTree->createElement('type');
        $cardTypeEl->appendChild($domTree->createTextNode($this->getCardBrand()));
        $cardEl->appendChild($cardTypeEl);

        $cardNameEl = $domTree->createElement('chname');
        $cardNameEl->appendChild($domTree->createTextNode($card->getBillingName()));
        $cardEl->appendChild($cardNameEl);

        $cvnEl = $domTree->createElement('cvn');

        $cvnNumberEl = $domTree->createElement('number');
        $cvnNumberEl->appendChild($domTree->createTextNode($card->getCvv()));
        $cvnEl->appendChild($cvnNumberEl);

        $presIndEl = $domTree->createElement('presind', 1);
        $cvnEl->appendChild($presIndEl);

        $cardEl->appendChild($cvnEl);

        $issueEl = $domTree->createElement('issueno');
        $issueEl->appendChild($domTree->createTextNode($card->getIssueNumber()));
        $cardEl->appendChild($issueEl);

        $root->appendChild($cardEl);

        $sha1El = $domTree->createElement('sha1hash');
        $sha1El->appendChild($domTree->createTextNode($sha1hash));
        $root->appendChild($sha1El);

        $xmlString = $domTree->saveXML($root);

        return $xmlString;
    }

    protected function createResponse($data)
    {
        /**
         * We need to inspect this response to see if the customer is actually
         * enrolled in the 3D Secure program. If they're not, we can go ahead
         * and do a normal auth instead.
         */
        $response = $this->response = new EnrolmentResponse($this, $data);

        if (!$response->isEnrolled()) {
            $request = new AuthRequest($this->httpClient, $this->httpRequest);
            $request->initialize($this->getParameters());

            $response = $request->send();
        }

        return $response;
    }

    public function getEndpoint()
    {
        return $this->getParameter('authEndpoint');
    }

    public function setAuthEndpoint($value)
    {
        return $this->setParameter('authEndpoint', $value);
    }
}
