<?php

require_once '../vendor/autoload.php';
use PHPHtmlParser\Dom\AbstractNode;

abstract class Subst {
    var $tag;

    abstract function start(AbstractNode $element):string;
    abstract function end(AbstractNode $element):string;
    function applies(AbstractNode $element):bool {
        return (strtolower($element->tag->name()) == strtolower($this->tag));
    }
}

class SimpleSubst extends Subst {
    var $start;
    var $end;

    function __construct($tag, $start, $end) {
        $this->tag = $tag;
        $this->start = $start;
        $this->end = $end;
    }

    function start(AbstractNode $element):string {
        return $this->start;
    }
    function end(AbstractNode $element):string {
        return $this->end;
    }

}


class TagBBCodeSubst extends SimpleSubst {

    function __construct($sourceTag, $outputTag, $bbTag) {
        parent::__construct($sourceTag,
            '<'.$outputTag."><s>[$bbTag]</s>",
            "<e>[/$bbTag]</e></".$outputTag.'>'
        );
    }
}

class SameSubst extends TagBBCodeSubst {

    function __construct($tag) {
        parent::__construct($tag, strtoupper($tag), $tag);
    }
}

class ImgSubst extends Subst {
    var $tag = 'img';

    function start(AbstractNode $element):string {
        $link = $this->get_link($element);
        $text = $element->text();
        return "<IMG src=\"$link\"><s>[img]</s><URL url=\"$link\"><LINK_TEXT text=\"$text\">";
    }
    function end(AbstractNode $element):string {
        return "</LINK_TEXT></URL><e>[/img]</e></IMG>";
    }

    private function get_link(AbstractNode $element) {
        $link = $element->getAttribute('src');
        if(!$link) $link = $element->getAttribute('data-src');

        return $link;
    }

}

class UrlSubst extends Subst {

    var $tag = 'url';
    var $linkAttName = 'href';

    function start(AbstractNode $element):string {
        $link = $element->getAttribute($this->linkAttName);
        $text = $element->plaintext;
        return "<URL url=\"$link\"><s>[url]</s><LINK_TEXT text=\"$text\">";
    }
    function end(AbstractNode $element):string {
        return "</LINK_TEXT><e>[/url]</e></URL><br/>";
    }
}

class EmptySubst extends SimpleSubst {
    function __construct($tag) {
        parent::__construct($tag, '', '');
    }
}

class VideoSubst extends Subst {
    var $tag = 'iframe';
    var $urlPattern;
    var $siteTag;
    var $idPattern;

    function __construct($urlPattern, $siteTag, $idPattern){
        $this->urlPattern = $urlPattern;
        $this->siteTag = $siteTag;
        $this->idPattern = $idPattern;
    }

    function applies(AbstractNode $element):bool {
        return ($element->tag->name() == $this->tag) &&
            (strpos($element->getAttribute('src'), $this->urlPattern) !== false);
    }

    function start(AbstractNode $element):string {
        $height = $element->getAttribute('height');
        $width = $element->getAttribute('width');
        $url = $element->getAttribute('src');
        $id = $this->get_id($url);

        $videoAtts = ($height && $width) ? "=$width,$height" : '';

        return '<'.$this->siteTag." id=\"$id\">".
            "<s>[bbvideo$videoAtts]</s><URL url=\"$url\">$url</URL>";
        // closed here to avoid something coming in between--^
    }

    function end(AbstractNode $element):string {
        return '<e>[/bbvideo]</e></'.$this->siteTag.'>';
    }

    function get_id($url) {
        if(preg_match($this->idPattern, $url, $matches))
            return $matches[1];

        return '';
    }
}


class MiaQuoteSubst extends Subst {
    var $tag = 'div';

    function applies(AbstractNode $element):bool {
        return ($element->tag->name() == $this->tag) &&
            ($element->getAttribute('class') == 'mia_bloque mia_cite');
    }

    function start(AbstractNode $element):string {
        return '<QUOTE><s>[quote]</s>';
    }

    function end(AbstractNode $element):string {
        return '<e>[/quote]</e></QUOTE>';
    }
}

class MiaSpoilerSubst extends Subst {
    var $tag = 'div';

    function applies(AbstractNode $element):bool {

        return ($element->tag->name() == $this->tag) &&
            ($element->getAttribute('class') == 'mia_bloque mia_spoiler');
    }

    function start(AbstractNode $element):string {
        return '<SPOIL><s>[spoil]</s>';
    }

    function end(AbstractNode $element):string {
        return '<e>[/spoil]</e></SPOIL>';
    }
}

class ColorSubst extends Subst {
    var $tag = 'span';
    var $stylePattern = '/color: #([0-9a-fA-F]+);/';
    var $matches = Array();


    function applies(AbstractNode $element):bool {

        return ($element->tag->name() == $this->tag) &&
            (preg_match($this->stylePattern, $element->getAttribute('style'), $this->matches));
    }

    function start(AbstractNode $element):string {

        if(count($this->matches)==0) {
            preg_match($this->stylePattern, $element->getAttribute('style'), $this->matches);
        }

        $colour = strtoupper($this->matches[1]);

        return "<COLOR color=\"#$colour\"><s>[color=#$colour]</s>";
    }

    function end(AbstractNode $element):string {
        return '<e>[/color]</e></COLOR>';
    }

}

// This must be always the last subst
class NoMatchSubst extends Subst {
    function applies(AbstractNode $element):bool {
        return true;
    }

    function start(AbstractNode $element):string {
        $result = '<'.$element->tag->name();

        foreach($element->getAttributes() as $name => $value) {
            $result .= " $name=\"$value\"";
        }
        $result .=">";

        return $result;
    }

    function end(AbstractNode $element):string {
        return '</'.$element->tag->name().'>';
    }

}