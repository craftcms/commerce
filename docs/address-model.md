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

### stateText
Alias of `getStateText()`
Returns the `stateName` if it exists, otherwise the related state's name.

### stateName

### countryId
The record ID of the related country (required).

### stateId
The record ID of the related state.

### stateText
Alias of getStateText()

### countryText
Alias of getCountryText()

# Methods

### getStateText()
Returns the `stateName` if it exists, otherwise the related state's name.

## getCountry()
Returns the related Country Model is it exists, or a blank string if it does not.

## getState()
Returns the related State Model is it exists, or null if it does not.
