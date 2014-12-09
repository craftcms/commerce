<?php
namespace Craft;


class Stripey_ChargeLegitModel extends BaseModel
{

    public function defineAttributes()
    {
        return array(
            'id'                    => AttributeType::Number,
            'stripeId'              => AttributeType::String,
            'created'               => AttributeType::DateTime,
            'livemode'              => AttributeType::Bool,
            'paid'                  => AttributeType::Bool,
            'amount'                => AttributeType::Number,
            'currency'              => AttributeType::String,
            'refunded'              => AttributeType::Bool,
            'captured'              => AttributeType::Bool,
            'refunds'               => AttributeType::Mixed,
            'balance_transaction'   => AttributeType::String,
            'failure_message'       => AttributeType::String,
            'failure_code'          => AttributeType::String,
            'amount_refunded'       => AttributeType::Number,
            'customer'              => AttributeType::String,
            'invoice'               => AttributeType::String,
            'description'           => AttributeType::String,
            'dispute'               => AttributeType::String,
            'metadata'              => AttributeType::Mixed,
            'statement_description' => AttributeType::String,
            'receipt_email'         => AttributeType::String,
            'receipt_number'        => AttributeType::String
        );
    }

    public function onAfterConstruct($event)
    {
        $charge = stripey()->api->stripe->charges()->find(array(
            'id' => $this->stripeId
        ));

        $this->setAttributes($charge);
    }
}