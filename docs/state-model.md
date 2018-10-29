# State Model

StateModel objects have the following attributes and methods:

## Attributes

### id

The address record ID.

### name

The countries name (required).

### formatName

The states’ name followed by the states’ country in parenthesis. For example:

`California (United States)`

### abbreviation

The states’s ISO code (required).

### country

The [Country model](country-model.md) of the country this state belongs to (required).

### countryId

The record ID of the country this state belongs to (required).

### cpEditUrl

A URL to edit this state in the Control Panel.

