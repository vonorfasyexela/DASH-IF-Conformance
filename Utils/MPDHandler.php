<?php

namespace DASHIF;

class MPDHandler
{
    private $url;
    private $mpd;
    private $dom;
    private $features;
    private $profiles;
    private $resolved;
    private $periodTimingInformation;
    private $schemaPath;
    private $mpdValidatorOutput;
    private $schematronOutput;
    private $schematronIssuesReport;

    private $selectedPeriod;
    private $selectedAdapationSet;
    private $selectedRepresentation;

    private $hls;
    private $hlsPlaylistArray;
    private $hlsManifestType;

    public function __construct($url)
    {
        $this->url = $url;
        $this->mpd = null;
        $this->dom = null;
        $this->features = null;
        $this->profiles = null;
        $this->resolved = null;
        $this->selectedPeriod = 0;
        $this->selectedAdaptationSet = 0;
        $this->selectedRepresentation = 0;
        $this->periodTimingInformation = array();
        $this->schemaPath = null;
        $this->mpdValidatorOutput = null;
        $this->schematronOutput = null;
        $this->schematronIssuesReport = null;

        echo("\tLoad URL...\n");
        $this->load();
        echo("\tURL loaded\n");
        if ($this->mpd){
            echo("\tExtracting features...\n");
            $this->features = $this->recursiveExtractFeatures($this->dom);
            echo("\tFeatures extracted\n");

            echo("\tExtracting profiles...\n");
            $this->extractProfiles();
            echo("\tProfiles extracted\n");

            echo("\tRunning Schematron...\n");
            $this->runSchematron();
            echo("\tSchematron finished\n");

            echo("\tValidate Schematron...\n");
            $this->validateSchematron();
            echo("\tSchematron validation done\n");
        }
    }

    public function selectPeriod($period)
    {
        $this->selectedPeriod = $period;
    }
    public function selectNextPeriod()
    {
        $this->selectedPeriod++;
    }
    public function getSelectedPeriod()
    {
        return $this->selectedPeriod;
    }

    public function selectAdaptationSet($adaptationSet)
    {
        $this->selectedAdaptationSet = $adaptationSet;
    }
    public function selectNextAdaptationSet()
    {
        $this->selectedAdaptationSet++;
    }
    public function getSelectedAdaptationSet()
    {
        return $this->selectedAdaptationSet;
    }

    public function selectRepresentation($representation)
    {
        $this->selectedRepresentation = $representation;
    }
    public function selectNextRepresentation()
    {
        $this->selectedRepresentation++;
    }
    public function getSelectedRepresentation()
    {
        return $this->selectedRepresentation;
    }

    public function getSchematronOutput()
    {
        return $this->schematronOutput;
    }

    private function runSchematron()
    {
        include 'impl/MPDHandler/runSchematron.php';
    }

    private function validateSchematron()
    {
        include 'impl/MPDHandler/validateSchematron.php';
    }

    private function findOrDownloadSchema()
    {
        include 'impl/MPDHandler/findOrDownloadSchema.php';
    }

    private function extractProfiles()
    {
        include 'impl/MPDHandler/extractProfiles.php';
    }

    private function recursiveExtractFeatures($node)
    {
        return include 'impl/MPDHandler/recursiveExtractFeatures.php';
    }

    public function getPeriodTimingInfo($periodIndex = null)
    {
        return include 'impl/MPDHandler/getPeriodTimingInfo.php';
    }

    private function getPeriodDurationInfo($period)
    {
        return include 'impl/MPDHandler/getPeriodDurationInfo.php';
    }

    private function getDurationForAllPeriods()
    {
        include 'impl/MPDHandler/getDurationsForAllPeriods.php';
    }

    public function getPeriodBaseUrl($periodIndex = null)
    {

        return include 'impl/MPDHandler/getPeriodBaseUrl.php';
    }

    public function getSegmentUrls($periodIndex = null)
    {
        return include 'impl/MPDHandler/getSegmentUrls.php';
    }

    private function computeTiming(
        $presentationDuration,
        $segmentAccess,
        $segmentAccessType
    ) {
        return include 'impl/MPDHandler/computeTiming.php';
    }

    private function computeDynamicIntervals(
        $adapatationSetId,
        $segmentAccess,
        $segmentTimings,
        $segmentCount
    ) {
        return include 'impl/MPDHandler/computeDynamicIntervals.php';
    }


    private function computeUrls(
        $representation,
        $adaptationSetId,
        $representationId,
        $segmentAccess,
        $segmentInfo,
        $baseUrl
    ) {
        return include 'impl/MPDHandler/computeUrls.php';
    }

    private function load()
    {
        include 'impl/MPDHandler/load.php';
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getMPD()
    {
        return $this->mpd;
    }


    public function getDom()
    {
        return $this->dom;
    }

    public function getResolved()
    {
        return $this->resolved;
    }

    public function getFeatures()
    {
        return $this->features;
    }

    public function getProfiles()
    {
        return $this->profiles;
    }

    public function getAllPeriodFeatures()
    {
        return $this->features['Period'];
    }

    public function getCurrentPeriodFeatures()
    {
        return $this->features['Period'][$this->selectedPeriod];
    }
}
