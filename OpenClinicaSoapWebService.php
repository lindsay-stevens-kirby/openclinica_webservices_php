<?php

/**
 * Class MTOMSoapClient
 *
 * Extension to remove the MTOM header, if any. This header interferes with
 * XML parsing.
 */
class MTOMSoapClient extends SoapClient
{
    /**
     * Check the response type, and if MTOM then remove the MTOM header.
     *
     * @param string $request The XML SOAP request.
     * @param string $location The URL to request.
     * @param string $action The SOAP action.
     * @param int $version The SOAP version.
     * @param int $one_way If one_way is set to 1, this method returns nothing.
     *  Use this where a response is not expected.
     * @return string
     */
    public function __doRequest(
        $request,
        $location,
        $action,
        $version,
        $one_way
    ) {
        $response = parent::__doRequest($request, $location, $action, $version,
            $one_way);
        // if response content type is mtom strip away everything but the xml.
        if (strpos($response, "Content-Type: application/xop+xml") !== false) {
            $SoapEnvStart = strpos($response, '<SOAP-ENV:Envelope');
            $SoapEnvEnd = strpos($response, '</SOAP-ENV:Envelope>') + 20;
            // 20 chars in strpos needle
            $response = substr($response, $SoapEnvStart,
                $SoapEnvEnd - $SoapEnvStart);
        }
        return $response;
    }
}

/**
 * Class WSSESecurityHeader
 *
 * Extension to add WSSE Security Header to the SOAP request.
 */
class WSSESecurityHeader extends SoapHeader
{
    /**
     * Create the security header for the SOAP XML message envelope.
     *
     * @param string $username OpenClinica username to send request as.
     * @param string $password SHA1 hash of user's password.
     */
    public function __construct($username, $password)
    {
        $wsseNamespace = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $wssePasswordNS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText';
        $wsseUserVar = new SoapVar($username, XSD_STRING, null, null,
            'Username', $wsseNamespace);
        $wssePassVar = new SoapVar($password, XSD_STRING, type, $wssePasswordNS,
            'Password', $wsseNamespace);
        $wsseUserPassVar = new SoapVar(array($wsseUserVar, $wssePassVar),
            SOAP_ENC_OBJECT, null, null, 'UsernameToken', $wsseNamespace);
        $security = new SoapVar(array($wsseUserPassVar), SOAP_ENC_OBJECT);
        parent::SoapHeader($wsseNamespace, 'Security', $security, 1);
    }
}

/**
 * Class OpenClinicaSoapWebService
 *
 * Handles calling the OpenClinica SOAP webservice methods.
 */
class OpenClinicaSoapWebService
{
    // Variables for the SoapClient object used in the requests
    const WSDL_STUDY = 'ws/study/v1/studyWsdl.wsdl';
    const WSDL_SED = 'ws/studyEventDefinition/v1/studyEventDefinitionWsdl.wsdl';
    const WSDL_DATA = 'ws/data/v1/dataWsdl.wsdl';
    const WSDL_EVENT = 'ws/event/v1/eventWsdl.wsdl';

    // WSDL locations within a webservice instance per OpenClinica documentation
    const WSDL_SSUBJ = 'ws/studySubject/v1/studySubjectWsdl.wsdl';
    const NS_OCBEANS = 'http://openclinica.org/ws/beans';
    const NS_ODM = 'http://www.cdisc.org/ns/odm/v1.3';
    private $ocWsInstanceURL;
    private $ocUserName;
    private $ocPassword;
    private $WSSESecurityHeader;

    /**
     * Set the common properties for sending SOAP requests.
     *
     * @param string $ocWsInstanceURL URL of the OpenClinica instance.
     * @param string $ocUserName OpenClinica username to send request as.
     * @param string $ocPassword SHA1 hash of user's password.
     */
    public function __construct($ocWsInstanceURL, $ocUserName, $ocPassword)
    {
        $this->ocWsInstanceURL = $ocWsInstanceURL;
        $this->ocUserName = $ocUserName;
        $this->ocPassword = $ocPassword;
        $this->WSSESecurityHeader = new WSSESecurityHeader($this->ocUserName,
            $this->ocPassword);
    }

    /**
     * List all the studies in OpenClinica that the user has access to.
     *
     * @return SimpleXMLElement Call result.
     */
    public function studyListAll()
    {
        $ocWsdlLocation = $this->ocWsInstanceURL . self::WSDL_STUDY;
        $ocWsdlNameSpace = 'http://openclinica.org/ws/study/v1';
        $ocSoapFunction = 'listAll';
        $ocSoapArguments = 'listAllRequest';
        $response = $this->callSoapClient($ocWsdlLocation, $ocWsdlNameSpace,
            $ocSoapFunction, $ocSoapArguments);
        return $response;
    }

    /**
     * Send the SOAP request and return the result.
     *
     * The SoapClient doesn't seem to correctly parse the name spaced responses,
     * so the the private __getLastResponse is called to get the raw result
     * and manually add the name spaces. In order to access this private
     * property, the "trace" attribute needs to be on.
     *
     * If the call is a complete failure then the catch echoes the call request
     * and result, so be careful with that.
     *
     * @param string $ocWsdlLocation Full URL of the SOAP method WSDL.
     * @param string $ocWsdlNameSpace Name space of the SOAP method WSDL.
     * @param string $ocSoapFunction Name of the SOAP method.
     * @param string $ocSoapArguments Arguments for the SOAP method.
     * @return SimpleXMLElement Call result.
     */
    private function callSoapClient(
        $ocWsdlLocation,
        $ocWsdlNameSpace,
        $ocSoapFunction,
        $ocSoapArguments
    ) {
        $ocSoapClient = new MTOMSoapClient($ocWsdlLocation,
            array('trace' => 1));
        $ocSoapClientHeader = $this->WSSESecurityHeader;
        $ocSoapClient->__setSoapHeaders($ocSoapClientHeader);
        try {
            $ocSoapClient->__soapCall($ocSoapFunction, array($ocSoapArguments));
            $response = simplexml_load_string(
                $ocSoapClient->__getLastResponse());
            $response->registerXPathNamespace('v1', $ocWsdlNameSpace);
            $response->registerXPathNamespace('odm', self::NS_ODM);
            return $response;
        } catch (SoapFault $soapfault) {
            echo 'last request: ' . $ocSoapClient->__getLastRequest();
            echo 'last response: ' . $ocSoapClient->__getLastResponse();
            die("soapfault: " . $soapfault);
        }
    }

    /**
     * Return the ODM metadata for the requested study.
     *
     * @param string $ocUniqueProtocolId Protocol ID of the study or site.
     * @return SimpleXMLElement Call result.
     */
    public function studyGetMetadata($ocUniqueProtocolId)
    {
        $ocWsdlLocation = $this->ocWsInstanceURL . self::WSDL_STUDY;
        $ocWsdlNameSpace = 'http://openclinica.org/ws/study/v1';
        $ocSoapFunction = 'getMetadata';

        $ocSoapArgIdentifier = new SoapVar($ocUniqueProtocolId, XSD_STRING,
            null, null, 'identifier', self::NS_OCBEANS);
        $ocSoapArgStudyRef = new SoapVar(array($ocSoapArgIdentifier),
            SOAP_ENC_OBJECT, null, null, 'studyRef', self::NS_OCBEANS);
        $ocSoapArgStudyMetaData = new SoapVar($ocSoapArgStudyRef,
            SOAP_ENC_OBJECT, null, $ocWsdlNameSpace, 'studyMetadata',
            $ocWsdlNameSpace);
        $ocSoapArguments = new SoapVar(array($ocSoapArgStudyMetaData),
            SOAP_ENC_OBJECT);
        $response = $this->callSoapClient($ocWsdlLocation, $ocWsdlNameSpace,
            $ocSoapFunction, $ocSoapArguments);
        return $response;
    }

    /**
     * Create a study subject in the specified study and/or site.
     *
     * @param string $ocUniqueProtocolId Protocol ID of the study to add to.
     * @param string $ocUniqueProtocolIDSiteRef (optional) site ID to add to.
     * @param string $ocStudySubjectId Study subject ID for subject.
     * @param string $ocSecondaryLabel Secondary label for subject.
     * @param string $ocEnrollmentDate Enrol date, ISO8601 format (2014-05-19).
     * @param string $ocPersonID Person ID for the subject.
     * @param string $ocGender Gender of subject, 'm' or 'f'.
     * @param string $ocDateOfBirth Birth date, ISO8601 format (2014-05-19).
     * @return SimpleXMLElement
     */
    public function subjectCreateSubject(
        $ocUniqueProtocolId,
        $ocUniqueProtocolIDSiteRef,
        $ocStudySubjectId,
        $ocSecondaryLabel,
        $ocEnrollmentDate,
        $ocPersonID,
        $ocGender,
        $ocDateOfBirth
    ) {
        $ocWsdlLocation = $this->ocWsInstanceURL . self::WSDL_SSUBJ;
        $ocWsdlNameSpace = 'http://openclinica.org/ws/studySubject/v1';
        $ocSoapFunction = 'create';

        // studyRef node
        $ocSoapVarStudyRef = $this->soapVarStudyRefSiteRef($ocUniqueProtocolId,
            $ocUniqueProtocolIDSiteRef);

        // subject node
        $ocSoapVarPersonID = new SoapVar($ocPersonID, XSD_STRING, null, null,
            'uniqueIdentifier', self::NS_OCBEANS);
        $ocSoapVarGender = new SoapVar($ocGender, XSD_STRING, null, null,
            'gender', self::NS_OCBEANS);
        // Assume 4 characters means we have just a year, not a full date.
        if (strlen($ocDateOfBirth) == 4) {
            $ocSoapVarDateOfBirth = new SoapVar ($ocDateOfBirth, XSD_STRING,
                null, null, 'yearOfBirth', self::NS_OCBEANS);
        } else {
            $ocSoapVarDateOfBirth = new SoapVar($ocDateOfBirth, XSD_DATE, null,
                null, 'dateOfBirth', self::NS_OCBEANS);
        }
        $ocSoapVarSubject = new SoapVar(
            array(
                $ocSoapVarPersonID,
                $ocSoapVarGender,
                $ocSoapVarDateOfBirth
            ), SOAP_ENC_OBJECT, null, self::NS_OCBEANS, 'subject',
            self::NS_OCBEANS);

        // studySubject node
        $ocSoapVarStudySubjectID = new SoapVar($ocStudySubjectId, XSD_STRING,
            null, null, 'label', self::NS_OCBEANS);
        $ocSoapVarSecondaryLabel = new SoapVar($ocSecondaryLabel, XSD_STRING,
            null, null, 'secondaryLabel', self::NS_OCBEANS);
        $ocSoapVarEnrollmentDate = new SoapVar($ocEnrollmentDate, XSD_DATE,
            null,
            null, 'enrollmentDate', self::NS_OCBEANS);
        $ocSoapVarStudySubject = new SoapVar(
            array(
                $ocSoapVarStudySubjectID,
                $ocSoapVarSecondaryLabel,
                $ocSoapVarEnrollmentDate,
                $ocSoapVarSubject,
                $ocSoapVarStudyRef
            ), SOAP_ENC_OBJECT, null, $ocWsdlNameSpace, 'studySubject',
            $ocWsdlNameSpace);

        $ocSoapArguments = new SoapVar(
            array(
                $ocSoapVarStudySubject
            ), SOAP_ENC_OBJECT);
        $response = $this->callSoapClient($ocWsdlLocation, $ocWsdlNameSpace,
            $ocSoapFunction, $ocSoapArguments);
        return $response;
    }

    /**
     * Create the studyRef and/or siteRef SoapVars, common to most calls.
     *
     * @param string $ocUniqueProtocolId Protocol ID of the study or site.
     * @param string $ocUniqueProtocolIDSiteRef Protocol ID of a study, if the
     *  particular call requires specifying both study and site.
     * @return SoapVar Variable ready for inclusion in a SOAP request.
     */
    private function soapVarStudyRefSiteRef(
        $ocUniqueProtocolId,
        $ocUniqueProtocolIDSiteRef
    ) {
        // ocSoapVarUniqueProtocolId is needed for both if,else cases below
        $ocSoapVarUniqueProtocolId = new SoapVar($ocUniqueProtocolId,
            XSD_STRING, null, self::NS_OCBEANS, 'identifier', self::NS_OCBEANS);
        if (preg_match('/\S/', $ocUniqueProtocolIDSiteRef) ||
            isset($ocUniqueProtocolIDSiteRef)
        ) {
            // if a siteRef has some non-whitespace content, or isSet
            // include siteRef node (empty() was not working for '' string)
            // must wrap single xsd_string in soapvar(array(obj),seo)
            $ocSoapVarUniqueProtocolIDSiteRef = new SoapVar(
                $ocUniqueProtocolIDSiteRef, XSD_STRING, null,
                self::NS_OCBEANS, 'identifier', self::NS_OCBEANS);
            $ocSoapVarUniqueProtocolIDSiteRefSEO = new SoapVar(
                array($ocSoapVarUniqueProtocolIDSiteRef), SOAP_ENC_OBJECT);
            $ocSoapVarUniqueProtocolIDSiteRefNode = new SoapVar(
                $ocSoapVarUniqueProtocolIDSiteRefSEO, SOAP_ENC_OBJECT, null,
                self::NS_OCBEANS, 'siteRef', self::NS_OCBEANS);
            // must use site's xsd_string soapvar here, not seo-wrapped one
            $ocSoapVarStudyRefArray = array(
                $ocSoapVarUniqueProtocolId,
                $ocSoapVarUniqueProtocolIDSiteRefNode
            );
            $ocSoapVarStudyRef = new SoapVar($ocSoapVarStudyRefArray,
                SOAP_ENC_OBJECT, null, self::NS_OCBEANS, 'studyRef',
                self::NS_OCBEANS);
        } else {
            // otherwise set the studyRef node with just the study ID
            $ocSoapVarUniqueProtocolIdSEO = new SoapVar(
                array($ocSoapVarUniqueProtocolId), SOAP_ENC_OBJECT);
            $ocSoapVarStudyRef = new SoapVar($ocSoapVarUniqueProtocolIdSEO,
                SOAP_ENC_OBJECT, null, self::NS_OCBEANS, 'studyRef',
                self::NS_OCBEANS);
        }
        return $ocSoapVarStudyRef;
    }

    /**
     * List all subjects in the specified study.
     *
     * Quirk of this SOAP method is that to return successfully, there must be
     * no blank personID, Date of Birth, or Sex for any subjects in the
     * OpenClinica instance.
     *
     * @param string $ocUniqueProtocolId Protocol ID of the study to add to.
     * @param string $ocUniqueProtocolIDSiteRef (optional) site ID to add to.
     * @return SimpleXMLElement Call result
     */
    public function subjectListAllByStudy(
        $ocUniqueProtocolId,
        $ocUniqueProtocolIDSiteRef
    ) {
        $ocWsdlLocation = $this->ocWsInstanceURL . self::WSDL_SSUBJ;
        $ocWsdlNameSpace = 'http://openclinica.org/ws/studySubject/v1';
        $ocSoapFunction = 'listAllByStudy';

        $ocSoapVarStudyRef = $this->soapVarStudyRefSiteRef($ocUniqueProtocolId,
            $ocUniqueProtocolIDSiteRef);
        $ocSoapArguments = new SoapVar(
            array(
                $ocSoapVarStudyRef
            ), SOAP_ENC_OBJECT);
        $response = $this->callSoapClient($ocWsdlLocation, $ocWsdlNameSpace,
            $ocSoapFunction, $ocSoapArguments);
        return $response;
    }

    /**
     * Check if the subject ID matches an existing subject in the study / site.
     *
     * @param string $ocUniqueProtocolId Protocol ID of the study.
     * @param string $ocUniqueProtocolIDSiteRef (optional) site ID.
     * @param string $ocStudySubjectId Study subject to check.
     * @return SimpleXMLElement
     */
    public function subjectIsStudySubject(
        $ocUniqueProtocolId,
        $ocUniqueProtocolIDSiteRef,
        $ocStudySubjectId
    ) {
        $ocWsdlLocation = $this->ocWsInstanceURL . self::WSDL_SSUBJ;
        $ocWsdlNameSpace = 'http://openclinica.org/ws/studySubject/v1';
        $ocSoapFunction = 'isStudySubject';

        $ocSoapVarStudySubjectID = new SoapVar($ocStudySubjectId, XSD_STRING,
            null, null, 'label', self::NS_OCBEANS);
        $ocSoapVarStudyRef = $this->soapVarStudyRefSiteRef($ocUniqueProtocolId,
            $ocUniqueProtocolIDSiteRef);
        $ocSoapVarStudySubject = new SoapVar(
            array(
                $ocSoapVarStudySubjectID,
                $ocSoapVarStudyRef
            ), SOAP_ENC_OBJECT, null, $ocWsdlNameSpace, 'studySubject',
            $ocWsdlNameSpace);
        $ocSoapArguments = new SoapVar(
            array(
                $ocSoapVarStudySubject
            ), SOAP_ENC_OBJECT);
        $response = $this->callSoapClient($ocWsdlLocation, $ocWsdlNameSpace,
            $ocSoapFunction, $ocSoapArguments);
        return $response;
    }

    /**
     * Create an event for the specified subject.
     *
     * @param string $ocStudySubjectId Study subject ID.
     * @param string $ocEventOID OID of the Event to add.
     * @param string $ocEventLocation Event location.
     * @param string $ocEventStartDate Event start date, ISO8601 format
     *  (2014-05-19).
     * @param string $ocEventStartTime Event start time, 24-hour format '12:59'.
     * @param string $ocEventEndDate Event end date, ISO8601 format (2014-05-19).
     * @param string $ocEventEndTime Event end time, 24-hour format '12:59'.
     * @param string $ocUniqueProtocolId Protocol ID of the study.
     * @param string $ocUniqueProtocolIDSiteRef (optional) site ID.
     * @return SimpleXMLElement
     */
    public function eventSchedule(
        $ocStudySubjectId,
        $ocEventOID,
        $ocEventLocation,
        $ocEventStartDate,
        $ocEventStartTime,
        $ocEventEndDate,
        $ocEventEndTime,
        $ocUniqueProtocolId,
        $ocUniqueProtocolIDSiteRef
    ) {
        $ocWsdlLocation = $this->ocWsInstanceURL . self::WSDL_EVENT;
        $ocWsdlNameSpace = 'http://openclinica.org/ws/event/v1';
        $ocSoapFunction = 'schedule';

        $ocSoapVarStudySubjectID = new SoapVar($ocStudySubjectId, XSD_STRING,
            null, null, 'label', self::NS_OCBEANS);
        $ocSoapVarStudySubjectRef = new SoapVar(array($ocSoapVarStudySubjectID),
            SOAP_ENC_OBJECT, null, self::NS_OCBEANS, 'studySubjectRef',
            self::NS_OCBEANS);

        $ocSoapVarStudyRef = $this->soapVarStudyRefSiteRef($ocUniqueProtocolId,
            $ocUniqueProtocolIDSiteRef);

        $ocSoapVarEventDefinitionOID = new SoapVar($ocEventOID, XSD_STRING,
            null, null, 'eventDefinitionOID', self::NS_OCBEANS);
        $ocSoapVarEventLocation = new SoapVar($ocEventLocation, XSD_STRING,
            null, null, 'location', self::NS_OCBEANS);
        $ocSoapVarEventStartDate = new SoapVar($ocEventStartDate, XSD_DATE,
            null, null, 'startDate', self::NS_OCBEANS);
        $ocSoapVarEventStartTime = new SoapVar($ocEventStartTime, XSD_STRING,
            null, null, 'startTime', self::NS_OCBEANS);
        $ocSoapVarEventEndDate = new SoapVar($ocEventEndDate, XSD_DATE, null,
            null, 'endDate', self::NS_OCBEANS);
        $ocSoapVarEventEndTime = new SoapVar($ocEventEndTime, XSD_STRING, null,
            null, 'endTime', self::NS_OCBEANS);
        $ocSoapVarEvent = new SoapVar(array(
            $ocSoapVarStudySubjectRef,
            $ocSoapVarStudyRef,
            $ocSoapVarEventDefinitionOID,
            $ocSoapVarEventLocation,
            $ocSoapVarEventStartDate,
            $ocSoapVarEventStartTime,
            $ocSoapVarEventEndDate,
            $ocSoapVarEventEndTime
        ), SOAP_ENC_OBJECT, null, $ocWsdlNameSpace, 'event', $ocWsdlNameSpace);
        $ocSoapArguments = new SoapVar(array($ocSoapVarEvent), SOAP_ENC_OBJECT);
        $response = $this->callSoapClient($ocWsdlLocation, $ocWsdlNameSpace,
            $ocSoapFunction, $ocSoapArguments);
        return $response;
    }

    /**
     * Insert CRF data for a specified study and/or site.
     *
     * The subject and event must already exist in OpenClinica.
     *
     * @param $ocODMClinicalData string ODM XML document to import.
     * @return SimpleXMLElement Call result.
     */
    public function dataImport($ocODMClinicalData)
    {
        $ocWsdlLocation = $this->ocWsInstanceURL . self::WSDL_DATA;
        $ocWsdlNameSpace = 'http://openclinica.org/ws/data/v1';
        $ocSoapFunction = 'import';

        $ocODMClinicalDataCDATA = '<![CDATA[' . $ocODMClinicalData . ']]>';
        $ocSoapVarODM = new SoapVar($ocODMClinicalData, XSD_ANYXML, null, null,
            'ODM', null);
        $ocSoapArguments = new SoapVar(array($ocSoapVarODM), SOAP_ENC_OBJECT);
        $response = $this->callSoapClient($ocWsdlLocation, $ocWsdlNameSpace,
            $ocSoapFunction, $ocSoapArguments);
        return $response;
    }

    /**
     * List all study event definitions for a study or site.
     *
     * @param $ocUniqueProtocolId string Protocol ID of the study or site.
     * @return SimpleXMLElement Call result.
     */
    public function studyEventDefinitionListAll($ocUniqueProtocolId)
    {
        $ocWsdlLocation = $this->ocWsInstanceURL . self::WSDL_SED;
        $ocWsdlNameSpace = 'http://openclinica.org/ws/studyEventDefinition/v1';
        $ocSoapFunction = 'listAll';

        $ocSoapVarStudyRef = $this->soapVarStudyRefSiteRef($ocUniqueProtocolId,
            '');
        $ocSoapVarSEDListAll = new SoapVar(array($ocSoapVarStudyRef),
            SOAP_ENC_OBJECT, null, $ocWsdlNameSpace,
            'studyEventDefinitionListAll', $ocWsdlNameSpace);
        $ocSoapArguments = new SoapVar(array($ocSoapVarSEDListAll),
            SOAP_ENC_OBJECT);
        $response = $this->callSoapClient($ocWsdlLocation, $ocWsdlNameSpace,
            $ocSoapFunction, $ocSoapArguments);
        return $response;
    }
}
