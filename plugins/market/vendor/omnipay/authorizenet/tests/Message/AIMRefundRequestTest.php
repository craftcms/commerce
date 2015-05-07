<?php

namespace Omnipay\AuthorizeNet\Message;

use Omnipay\Tests\TestCase;

class AIMRefundRequestTest extends TestCase
{
    public function setUp()
    {
        $this->request = new AIMRefundRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize(
            array(
                'amount' => '12.00',
                'transactionReference' => '60O2UZ',
                'currency' => 'USD',
                'card' => $this->getValidCard(),
            )
        );
    }

    public function testGetData()
    {
        $data = $this->request->getData();

        $card = $this->getValidCard();

        $this->assertSame('CREDIT', $data['x_type']);
        $this->assertSame('60O2UZ', $data['x_trans_id']);
        $this->assertSame($card['number'], $data['x_card_num']);
        $this->assertSame('12.00', $data['x_amount']);
    }
}
