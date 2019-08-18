<?php

require_once('subst.class.php');

abstract class VideoSubstFactory {
    abstract function getSubst(string $urlPattern, string $siteTag, string $idPattern): VideoSubst;
}

class VideoSubst314Factory extends VideoSubstFactory {
    function getSubst(string $urlPattern, string $siteTag, string $idPattern): VideoSubst {
        return new VideoSubst_314($urlPattern);
    }
}

class VideoSubst322Factory extends VideoSubstFactory {
    function getSubst(string $urlPattern, string $siteTag, string $idPattern): VideoSubst {
        return new VideoSubst_322($urlPattern, $siteTag, $idPattern);
    }
}
