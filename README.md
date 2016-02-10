# OpenClinica Webservices Client - PHP


## Introduction
A client for the OpenClinica SOAP webservices, in PHP. Includes a script to 
help build an ODM XML object for data import.

See the examples script for how calls are made.

There are a couple of tests, but in general that is the big TODO item. The
tests would need to mock the expected input / output. Integration tests would
require an accessible OpenClinica instance that can be reset easily.


## Contributing
Please do the following, it makes it much easier to review and accept PRs.
- Include tests and PHPDoc strings for new code, or update existing.
- Follow the same naming conventions.
- Follow the same code formatting; this uses PSR2 style preset in Intellij.


## Other Implementations
- Python client + desktop app: https://github.com/toskrip/open-clinica-scripts
- Python client: https://github.com/lindsay-stevens-kirby/openclinica_webservices_py
- Python client: https://github.com/dimagi/openclinica-xforms/blob/master/webservices.py
- Java: https://github.com/jacobrousseau/traitocws/blob/master/TraITOCWS/src/nl/vumc/trait/oc/connect/OCWebServices.java


## Previous version
This used to live in the openclinica_scripts repository [1]. This version is
updated with PHPDoc strings all over, some convenience functions for building
ODM objects, and the inclusion of the FormStatus attribute which is processed
by OpenClinica 3.6 and up.

[1] https://github.com/lindsay-stevens-kirby/openclinica_scripts/tree/master/webservices/php


## Thanks
Csaba Halmagyi for suggested enhancements.