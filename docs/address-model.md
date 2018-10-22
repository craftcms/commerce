# Address Model

AddressModel's have the following attributes and methods:

# Attributes

### id
The address record ID.

### firstName
The customers first name (required).

### lastName
The customers last name (required).

### address1
The first line of the address,

### address2
Second address line.

### city
The city.

### zipCode
The zip or postcode.

### phone
Phone number.

### alternativePhone
An alternative phone number

### businessName
Business Name.

### businessTaxId
Business Tax ID. No automatic validation of this ID occurs.

### stateId
The record ID of the related state, if one was related to the address. 

### stateName
Returns the state name saved on the address. This will be null if the `stateId` was set.

### stateText
Read only. Alias of `getStateText()`  
Returns the related state's name based on the `stateId`, or the `stateName` if `stateId` is null.

### countryId
The record ID of the related country (required).

### countryText
Read only. Alias of `getCountryText()`  
Returns the related country's name based on the `countryId`.

# Methods

### getState()
Returns the related State Model is it exists, or null if it does not.

### getStateText()
Returns the related state's name based on the stateId, or the stateName if stateId is null.

### getCountry()
Returns the related Country Model.

### getCountryText()
Returns the related country's name based on the `countryId`.

### setStateValue($value)
Sets the `stateId` or `stateName`.  
Accepts either a valid state ID or a free-text state name. 

# States

You can create states in a relationship with countries within the Control Panel.

Not all states need to be created in the Control Panel, unless you have enforced a real state to exist when setting up the country. A free-text state name can be submitted and stored in the `stateName` attribute.

The address model allows for free-text state text to be submitted instead of a `stateId`. When submitting the state value from a form there is a special `stateValue` param available for convenience. It accepts either a valid state ID or a free-text state name.

# Validation

By default the `firstName`, `lastName` and `countryId` are required attributes when saving an address.

Using the `Address::EVENT_REGISTER_ADDRESS_VALIDATION_RULES` event, you can modify the rules array.

 ```php
  use craft\commerce\events\RegisterAddressRulesEvent;
  use craft\commerce\models\Address;
  
  Event::on(Address::class, Address::EVENT_REGISTER_ADDRESS_VALIDATION_RULES, function(RegisterAddressRulesEvent $event) {
    $event->rules[] = [['attention'], 'required'];
  });
```

