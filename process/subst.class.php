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

    var $tag = 'a';
    var $linkAttName = 'href';

    function start(AbstractNode $element):string {
        $link = $element->getAttribute($this->linkAttName);
        $text = $element->text;

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

abstract class VideoSubst extends Subst {
    var $tag = 'iframe';
    var $urlPattern;
    var $siteTag;

    abstract function video_start(string $url, string $width, string $height);
    abstract function video_end(string $url);

    function __construct($urlPattern){
        $this->urlPattern = $urlPattern;
    }

    function applies(AbstractNode $element):bool {
        return ($element->tag->name() == $this->tag) &&
            (strpos($element->getAttribute('src'), $this->urlPattern) !== false);
    }

    function start(AbstractNode $element):string {
        $height = $element->getAttribute('height');
        $width = $element->getAttribute('width');
        $url = $element->getAttribute('src');

        return $this->video_start($url, $width, $height);
    }

    function end(AbstractNode $element):string {
        $url = $element->getAttribute('src');
        return $this->video_end($url);
    }

}

class VideoSubst_322 extends VideoSubst {
    var $idPattern;

    function __construct($urlPattern, $siteTag, $idPattern){
        parent::__construct($urlPattern);
        $this->idPattern = $idPattern;
        $this->siteTag = $siteTag;
    }

    function video_start(string $url, string $width, string $height) {
        $videoAtts = ($height && $width) ? "=$width,$height" : '';
        $id = $this->get_id($url);

        return '<'.$this->siteTag." id=\"$id\">".
            "<s>[bbvideo$videoAtts]</s><URL url=\"$url\">$url</URL>";
        // closed here to avoid something coming in between--^
    }

    function video_end(string $url) {
        return '<e>[/bbvideo]</e></'.$this->siteTag.'>';
    }

    private function get_id($url) {
        if(preg_match($this->idPattern, $url, $matches))
            return $matches[1];

        return '';
    }

}

class VideoSubst_314 extends VideoSubst {
    function video_start(string $url, string $width, string $height) {
        $videoAtts = ($height && $width) ? "=$width,$height" : '';
        $id = $this->get_id($url);

        return "<BBVIDEO bbvideo0=\"$width\" bbvideo1=\"$height\" content=\"$url\">".
            "<s>[BBvideo=$width,$height]</s>$url";
    }

    function video_end(string $url) {
        return '<e>[/BBvideo]</e></BBVIDEO>';
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

abstract class WithStyleSubst extends Subst {
    var $pattern = '/ *([^ :]+) *: *(.*)/';

    function start(AbstractNode $element): string {
        $expressions = explode(';', $element->getAttribute('style'));

        $result ='';
        foreach($expressions as $expression) {
            preg_match($this->pattern, $expression, $matches);
            if(count($matches) > 0) {
                switch(strtolower($matches[1])) {
                    case('font-family'): $result .= SpanSubst::font_start($matches[2]); break;
                    case('text-decoration'): $result .= SpanSubst::text_decoration_start($matches[2]); break;
                    case('color'):
                    case('colour'): $result .= SpanSubst::colour_start($matches[2]); break;
                    case('text-align'): $result .= SpanSubst::text_align_start($matches[2]); break;
                }
            }
        }
        return $result;
    }

    function end(AbstractNode $element): string {
        $expressions = explode(';', $element->getAttribute('style'));

        $result ='';
        foreach($expressions as $expression) {
            preg_match($this->pattern, $expression, $matches);
            if(count($matches) > 0) {
                switch(strtolower($matches[1])) {
                    case('font-family'): $result .= SpanSubst::font_end($matches[2]); break;
                    case('text-decoration'): $result .= SpanSubst::text_decoration_end($matches[2]); break;
                    case('color'):
                    case('colour'): $result .= SpanSubst::colour_end($matches[2]); break;
                    case('text-align'): $result .= SpanSubst::text_align_end($matches[2]); break;
                }
            }
        }
        return $result;
    }

    static function font_start($value) {
        return "<FONT font=\"$value\"><s>[font=$value]</s>";
    }

    static function font_end($value) {
        return '<e>[/font]</e></FONT>';
    }

    static function text_decoration_start($value) {
        switch($value) {
            case 'line-through': return '<S><s>[s]</s>';
            case 'underline': return '<U><s>[u]</s>';
        }
    }

    static function text_decoration_end($value) {
        switch($value) {
            case 'line-through': return '<e>[/s]</e></S>';
            case 'underline': return '<e>[/u]</e></U>';
        }
    }

    static function colour_start($value) {
        return "<COLOR color=\"$value\"><s>[color=$value]</s>";
    }

    static function colour_end($value) {
        return '<e>[/color]</e></COLOR>';
    }

    static function text_align_start($value) {
        return "<ALIGN align=\"$value\"><s>[align=$value]</s>";
    }

    static function text_align_end($value) {
        return '<e>[/align]</e></ALIGN>';
    }

}

class SpanSubst extends WithStyleSubst {
    var $tag = 'span';
}

class PSubst extends WithStyleSubst {
    var $tag = 'p';
    function end(AbstractNode $element): string {
        return parent::end($element)."<br/>\n<br/>\n";
    }
}

class HeadingSubst extends SimpleSubst{
    function __construct($hn, $size) {
        parent::__construct("h$hn", "<SIZE size=\"$size\"><s>[size=$size]</s><B><s>[b]</s>",
            "<e>[/b]</e></B><e>[/size]</e></SIZE><br/>\n<br/>\n"
        );
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