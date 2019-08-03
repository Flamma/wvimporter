<?php

require_once '../vendor/autoload.php';
use PHPHtmlParser\Dom;
use PHPHtmlParser\Dom\AbstractNode;
use PHPHtmlParser\Dom\InnerNode;

function transform_content($content) {
    $result = $content;
    $result = enclose($result);
    $result = replace_no_href_links($result); // Due to a bug on HTMLConverter
    $result = replace_nbsp($result);
    $result = html_to_db_bbcode($result);

    return $result;
}


function replace_no_href_links($content) {
    preg_match_all("/<a([^>]*)>/", $content, $matches, PREG_SET_ORDER);

    foreach($matches as $matching) {
        if(strpos($matching[1], "href") === FALSE)
            $content = str_replace($matching[0], '<a href="#" '.$matching[1].'>', $content);
    }

    return $content;
}

function replace_nbsp($content) {
    return str_replace('&nbsp;', ' ', $content);
}

function enclose($content) {
    return "<r>$content</r>";
}

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

function get_substs() {
    return Array(
        new SimpleSubst('p', '', "<br/>\n<br/>\n"),
        new EmptySubst('text'),
        new SameSubst('b'),
        new TagBBCodeSubst('strong','B', 'b'),
        new SameSubst('i'),
        new TagBBCodeSubst('em','I', 'i'),
        new SameSubst('code'),
        new SameSubst('quote'),
        new ImgSubst(),
        new UrlSubst(),
        new MiaQuoteSubst(),
        new MiaSpoilerSubst(),
        new VideoSubst('youtube.com/embed', 'YOUTUBE', '#youtube.com/embed/([a-zA-Z0-9_-]+)#'),
        new VideoSubst('youtube.com', 'YOUTUBE', '#youtube.com/watch?v=([a-zA-Z0-9_-]+)#'),
        new VideoSubst('vimeo', 'VIMEO', '#vimeo.com/([0-9]+)#'),
        new VideoSubst('twitch.tv', 'TWITCH', '#videos/[0-9]+#'),
        new NoMatchSubst() // This should always be the last one
    );
}

function apply_substs(AbstractNode $element, $substs) {
    foreach($substs as $subst) {
        if($subst->applies($element)) return Array($subst->start($element), $subst->end($element));
    }

    // This should never happen
    return Array('<UNKNOWNTAG>', '</UNKNOWNTAG>');

}

function parse_element(AbstractNode $element, $substs) {

    $start_end = apply_substs($element, $substs);
    $start = $start_end[0];
    $end = $start_end[1];

    if($element instanceOf InnerNode && $element->hasChildren()) {
        $middle = '';

        foreach($element->getChildren() as $child) {
            $middle .= parse_element($child, $substs);
        }

    } else {
        $middle = $element->text();
    }

    return $start.$middle.$end;
}

function html_to_db_bbcode($content) {

    $dom = new Dom;

    // Load HTML from a string
    $dom->load($content);

    $substs = get_substs();

    $root = $dom->find('r')[0];

    return parse_element($root, $substs);
}

