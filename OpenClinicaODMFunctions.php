<?php

/**
 * Class ocODMclinicalData
 *
 * A collection of subjects.
 */
class ocODMclinicalData
{
    /**
     * @var string Study OID.
     */
    public $studyOID;
    /**
     * @var string Identifier for the metadata version. Can be anything.
     */
    public $metaDataVersionOID;
    /**
     * @var ocODMsubjectData[] Array of subject objects.
     */
    public $subjectData;

    /**
     * @param string $studyOID Study OID.
     * @param string $metaDataVersionOID Identifier for the metadata version.
     *  Can be anything.
     * @param ocODMsubjectData[] $subjectData Array of subject objects.
     */
    public function __construct($studyOID, $metaDataVersionOID, $subjectData)
    {
        $this->studyOID = $studyOID;
        $this->metaDataVersionOID = $metaDataVersionOID;
        $this->subjectData = $subjectData;
    }

    /**
     * Update or insert an item value. this->Subject->Event->Form->Group->Item
     * ->Value.
     *
     * @param string $subjectKey Study Subject OID.
     * @param string $studyEventOID Study Event OID.
     * @param string $studyEventRepeatKey Study Event Repeat Key.
     * @param string $formOID Form OID.
     * @param string $formStatus Form Status.
     * @param string $itemGroupOID Item Group OID.
     * @param string $itemGroupRepeatKey Item Group Repeat Key.
     * @param string $itemOID Item OID.
     * @param string $itemValue Item value.
     */
    public function upsert_item(
        $subjectKey,
        $studyEventOID,
        $studyEventRepeatKey,
        $formOID,
        $itemGroupOID,
        $itemGroupRepeatKey,
        $itemOID,
        $itemValue,
        $formStatus = null
    ) {
        if (isset($this->subjectData[$subjectKey])) {
            $this->subjectData[$subjectKey]->upsert_item($studyEventOID,
                $studyEventRepeatKey, $formOID, $itemGroupOID,
                $itemGroupRepeatKey, $itemOID, $itemValue, $formStatus);
        } else {
            $this->subjectData[$subjectKey] = new ocODMsubjectData($subjectKey,
                array());
            $this->subjectData[$subjectKey]->upsert_item($studyEventOID,
                $studyEventRepeatKey, $formOID, $itemGroupOID,
                $itemGroupRepeatKey, $itemOID, $itemValue, $formStatus);
        }
    }

    /**
     * Return the ocODMclinicalData as an XML string, for import to OpenClinica.
     *
     * It's a pretty redundant method tbh but makes it clear. Is a separate
     * function to ODMtoDOMDocument so that the result could be further
     * modified without having to parse the XML again.
     *
     * @returns string ODM XML for import to OpenClinica.
     */
    function toXML()
    {
        return $this->ODMtoDOMDocument()->saveXML();
    }

    /**
     * Converts this ocODMclinicalData object into a DOMDocument.
     *
     * @return DOMDocument ODM DOMDocmuent tree. Call ->saveXML() for string.
     */
    function ODMtoDOMDocument()
    {
        $document = new DOMDocument();
        $elementODM = $document->createElement('ODM');
        $document->appendChild($elementODM);
        $this->appendToDOMElement($document, $elementODM);
        return $document;
    }

    /**
     * Add the object to the given document, and append to the given element.
     *
     * @param DOMDocument $domDocument Document being modified.
     * @param DOMElement $domElement Element to append to this object to.
     */
    public function appendToDOMElement($domDocument, $domElement)
    {
        $element = $domDocument->createElement('ClinicalData');
        $domElement->appendChild($element);
        $element->setAttribute('StudyOID', $this->studyOID);
        $element->setAttribute('MetaDataVersionOID', $this->metaDataVersionOID);
        foreach ($this->subjectData as $child) {
            $child->appendToDOMElement($domDocument, $element);
        }
    }
}

/**
 * Class ocODMsubjectData
 *
 * A collection of events.
 */
class ocODMsubjectData
{
    /**
     * @var string Study Subject OID.
     */
    public $subjectKey;
    /**
     * @var ocODMstudyEventData[] Array of event objects.
     */
    public $studyEventData;

    /**
     * @param string $subjectKey Study Subject OID.
     * @param ocODMstudyEventData[] $studyEventData Array of event objects.
     */
    public function __construct($subjectKey, $studyEventData)
    {
        $this->subjectKey = $subjectKey;
        $this->studyEventData = $studyEventData;
    }

    /**
     * Update or insert an item value. this->Event->Form->Group->Item->Value.
     *
     * @param string $studyEventOID Study Event OID.
     * @param string $studyEventRepeatKey Study Event Repeat Key.
     * @param string $formOID Form OID.
     * @param string $formStatus Form Status.
     * @param string $itemGroupOID Item Group OID.
     * @param string $itemGroupRepeatKey Item Group Repeat Key.
     * @param string $itemOID Item OID.
     * @param string $itemValue Item value.
     */
    public function upsert_item(
        $studyEventOID,
        $studyEventRepeatKey,
        $formOID,
        $itemGroupOID,
        $itemGroupRepeatKey,
        $itemOID,
        $itemValue,
        $formStatus = null
    ) {
        $key = "" . $studyEventOID . $studyEventRepeatKey;
        if (isset($this->studyEventData[$key])) {
            $this->studyEventData[$key]->upsert_item($formOID, $itemGroupOID,
                $itemGroupRepeatKey, $itemOID, $itemValue, $formStatus);
        } else {
            $this->studyEventData[$key] = new ocODMstudyEventData($studyEventOID,
                $studyEventRepeatKey, array());
            $this->studyEventData[$key]->upsert_item($formOID, $itemGroupOID,
                $itemGroupRepeatKey, $itemOID, $itemValue, $formStatus);
        }
    }

    /**
     * Add the object to the given document, and append to the given element.
     *
     * @param DOMDocument $domDocument Document being modified.
     * @param DOMElement $domElement Element to append to this object to.
     */
    public function appendToDOMElement($domDocument, $domElement)
    {
        $element = $domDocument->createElement('SubjectData');
        $domElement->appendChild($element);
        $element->setAttribute('SubjectKey', $this->subjectKey);
        foreach ($this->studyEventData as $child) {
            $child->appendToDOMElement($domDocument, $element);
        }
    }
}

/**
 * Class ocODMstudyEventData
 *
 * A collection of forms.
 */
class ocODMstudyEventData
{
    /**
     * @var string Study Event OID.
     */
    public $studyEventOID;
    /**
     * @var string Identifier of repeatable event instances. Is actually a
     *  positive integer, counting from 1.
     */
    public $studyEventRepeatKey;
    /**
     * @var ocODMformData[] Array of form objects.
     */
    public $formData;

    /**
     * @param string $studyEventOID Study Event OID.
     * @param string $studyEventRepeatKey Study Event Repeat Key.
     * @param ocODMformData[] $formData Array of forms.
     */
    public function __construct($studyEventOID, $studyEventRepeatKey, $formData)
    {
        $this->studyEventOID = $studyEventOID;
        $this->studyEventRepeatKey = $studyEventRepeatKey;
        $this->formData = $formData;
    }

    /**
     * Update or insert an item value. this->Form->Group->Item->Value.
     *
     * @param string $formOID Form OID.
     * @param string $formStatus Form Status.
     * @param string $itemGroupOID Item Group OID.
     * @param string $itemGroupRepeatKey Item Group Repeat Key.
     * @param string $itemOID Item OID.
     * @param string $itemValue Item value.
     */
    public function upsert_item(
        $formOID,
        $itemGroupOID,
        $itemGroupRepeatKey,
        $itemOID,
        $itemValue,
        $formStatus = null
    ) {
        if (isset($this->formData[$formOID])) {
            $this->formData[$formOID]->upsert_item($itemGroupOID,
                $itemGroupRepeatKey, $itemOID, $itemValue);
        } else {
            $this->formData[$formOID] = new ocODMformData($formOID, array(),
                $formStatus);
            $this->formData[$formOID]->upsert_item($itemGroupOID,
                $itemGroupRepeatKey, $itemOID, $itemValue);
        }
    }

    /**
     * Add the object to the given document, and append to the given element.
     *
     * @param DOMDocument $domDocument Document being modified.
     * @param DOMElement $domElement Element to append to this object to.
     */
    public function appendToDOMElement($domDocument, $domElement)
    {
        $element = $domDocument->createElement('StudyEventData');
        $domElement->appendChild($element);
        $element->setAttribute('StudyEventOID', $this->studyEventOID);
        $element->setAttribute('StudyEventRepeatKey',
            $this->studyEventRepeatKey);
        foreach ($this->formData as $child) {
            $child->appendToDOMElement($domDocument, $element);
        }
    }
}

/**
 * Class ocODMformData
 *
 * A collection of item groups.
 */
class ocODMformData
{
    /**
     * @var string Form OID.
     */
    public $formOID;
    /**
     * @var ocODMitemGroupData[] Array of item group objects.
     */
    public $itemGroupData;
    /**
     * @var null|string Form status to set (Available in OpenClinica 3.6+)
     */
    public $formStatus;

    /**
     * @param string $formOID Form OID.
     * @param string $formStatus Form Status.
     * @param ocODMitemGroupData[] $itemGroupData Array of item groups.
     */
    public function __construct($formOID, $itemGroupData, $formStatus = null)
    {
        $this->formOID = $formOID;
        $this->itemGroupData = $itemGroupData;
        $this->formStatus = $formStatus;
    }

    /**
     * Update or insert an item value. this->Group->Item->Value.
     *
     * @param string $itemGroupOID Item Group OID.
     * @param string $itemGroupRepeatKey Item Group Repeat Key.
     * @param string $itemOID Item OID.
     * @param string $itemValue Item value.
     */
    public function upsert_item(
        $itemGroupOID,
        $itemGroupRepeatKey,
        $itemOID,
        $itemValue
    ) {
        $key = "" . $itemGroupOID . $itemGroupRepeatKey;
        if (isset($this->itemGroupData[$key])) {
            $this->itemGroupData[$key]->upsert_item($itemOID, $itemValue);
        } else {
            $this->itemGroupData[$key] = new ocODMitemGroupData(
                $itemGroupOID, $itemGroupRepeatKey, array());
            $this->itemGroupData[$key]->upsert_item($itemOID, $itemValue);
        }
    }

    /**
     * Add the object to the given document, and append to the given element.
     *
     * TODO: full set of valid status mappings.
     *
     * @param DOMDocument $domDocument Document being modified.
     * @param DOMElement $domElement Element to append to this object to.
     */
    public function appendToDOMElement($domDocument, $domElement)
    {
        $element = $domDocument->createElement('FormData');
        $domElement->appendChild($element);
        if ($this->formStatus == 'dataentrystarted') {
            $element->setAttribute('OpenClinica:Status', 'initial data entry');
        } else {
            $element->setAttribute('OpenClinica:Status', $this->formStatus);
        }
        $element->setAttribute('FormOID', $this->formOID);
        foreach ($this->itemGroupData as $child) {
            $child->appendToDOMElement($domDocument, $element);
        }
    }
}

/**
 * Class ocODMitemGroupData
 *
 * A collection of item(s).
 */
class ocODMitemGroupData
{
    /**
     * @var string Item Group OID.
     */
    public $itemGroupOID;
    /**
     * @var string Identifier of repeatable item instances. Is actually a
     *  positive integer, counting from 1.
     */
    public $itemGroupRepeatKey;
    /**
     * @var ocODMitemData[] Array of item objects.
     */
    public $itemData;

    /**
     * @param string $itemGroupOID Item Group OID.
     * @param string $itemGroupRepeatKey Item Group Repeat Key.
     * @param ocODMitemData[] $itemData Array of items.
     */
    public function __construct($itemGroupOID, $itemGroupRepeatKey, $itemData)
    {
        $this->itemGroupOID = $itemGroupOID;
        $this->itemGroupRepeatKey = $itemGroupRepeatKey;
        $this->itemData = $itemData;
    }

    /**
     * Update or insert an item value. this->Item->Value.
     *
     * @param string $itemOID Item OID.
     * @param string $itemValue Item value.
     */
    public function upsert_item($itemOID, $itemValue)
    {
        if (isset($this->itemData[$itemOID])) {
            $this->itemData[$itemOID]->itemValue = $itemValue;
        } else {
            $this->itemData[$itemOID] = new ocODMitemData($itemOID, $itemValue);
        }
    }

    /**
     * Add the object to the given document, and append to the given element.
     *
     * @param DOMDocument $domDocument Document being modified.
     * @param DOMElement $domElement Element to append to this object to.
     */
    public function appendToDOMElement($domDocument, $domElement)
    {
        $element = $domDocument->createElement('ItemGroupData');
        $domElement->appendChild($element);
        $element->setAttribute('ItemGroupOID', $this->itemGroupOID);
        $element->setAttribute('ItemGroupRepeatKey', $this->itemGroupRepeatKey);
        $element->setAttribute('TransactionType', 'Insert');
        foreach ($this->itemData as $child) {
            $child->appendToDOMElement($domDocument, $element);
        }
    }
}

/**
 * Class ocODMitemData
 *
 * Represents items and their values.
 */
class ocODMitemData
{
    /**
     * @var string Item OID.
     */
    public $itemOID;
    /**
     * @var string Item value.
     */
    public $itemValue;

    /**
     * @param string $itemOID Item OID.
     * @param string $itemValue Item value.
     */
    public function __construct($itemOID, $itemValue)
    {
        $this->itemOID = $itemOID;
        $this->itemValue = $itemValue;
    }

    /**
     * Add the object to the given document, and append to the given element.
     *
     * @param DOMDocument $domDocument Document being modified.
     * @param DOMElement $domElement Element to append to this object to.
     */
    public function appendToDOMElement($domDocument, $domElement)
    {
        $element = $domDocument->createElement('ItemData');
        $domElement->appendChild($element);
        $element->setAttribute('ItemOID', $this->itemOID);
        $element->setAttribute('Value', $this->itemValue);
    }
}


/**
 * Class Tests
 *
 * Some basic tests.
 */
class Tests
{

    /**
     * Check that the expected structures can be converted to XML, print result.
     *
     * This exercises the DOM related functions, since they get called in a
     * chain to prepare the final document.
     *
     * TODO: assert against expected XML.
     * TODO: split into individual unit tests.
     */
    function test_xml_from_odm_tree()
    {
        $itemGroupRow1 = new ocODMitemGroupData('IG_C1107_SCREEN', 1,
            array(
                new ocODMitemData('I_C1107_VISIT_DT', '2014-05-17'),
                new ocODMitemData('I_C1107_INC1_CHR', 0)
            ));
        $itemGroupRow2 = new ocODMitemGroupData('IG_C1107_SCREEN', 2,
            array(
                new ocODMitemData('I_C1107_VISIT_DT', '2014-05-16'),
                new ocODMitemData('I_C1107_INC1_CHR', 1)
            ));

        $odm = new ocODMclinicalData('S_V1107', 1,
            array(
                new ocODMsubjectData('SS_1107TEST_2732',
                    array(
                        new ocODMstudyEventData(
                            'SE_V1107_SCREENING', 1,
                            array(
                                new ocODMformData(
                                    'F_C1107_SCREEN_1',
                                    array(
                                        $itemGroupRow1,
                                        $itemGroupRow2
                                    ))
                            ))
                    ))
            )
        );
        print $odm->toXML();
    }

    /**
     * Check that the upsert_item shortcut works.
     *
     * This exercises the upsert related functions, since they get called in a
     * chain to update the object.
     *
     * TODO: assert against expected XML.
     * TODO: split into individual unit tests.
     */
    public function test_upsert_cascade()
    {
        $odm = new ocODMclinicalData('S_V1107', 1, array());
        $odm->upsert_item('1', '2', '3', '4', '5', '6', '7', '8', 'new');
        $odm->upsert_item('1', '2', '3', '4', '5', '6', '7', '8', 'new');
        $odm->upsert_item('1', '2', '3', '4', '5', '6', 'M', 'H', 'new');
        $odm->upsert_item('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'new');
        print $odm->toXML();
    }
}

$tests = new Tests();
$tests->test_xml_from_odm_tree();
$tests->test_upsert_cascade();
