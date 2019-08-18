<?php

require_once '../vendor/autoload.php';
require_once 'subst.class.php';
require_once '../config/extensions.php';
require_once 'video_subst_factory.php';

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


function get_substs() {
    global $VERSIONS;

    $videoFactory = $VERSIONS['video'] == '3.1.4' ?
        new VideoSubst314Factory() :
        new VideoSubst322Factory()
    ;

    return Array(
        new PSubst(),
        new EmptySubst('text'),
        new SameSubst('b'),
        new SameSubst('s'),
        new TagBBCodeSubst('strong','B', 'b'),
        new SameSubst('i'),
        new TagBBCodeSubst('em','I', 'i'),
        new SameSubst('code'),
        new SameSubst('quote'),
        new ImgSubst(),
        new UrlSubst(),
        new SameSubst('u'),
        new SimpleSubst('ol', '<LIST type="decimal"><s>[list=1]</s>', '<e>[/list]</e></LIST>'),
        new SimpleSubst('ul', '<LIST><s>[list]</s>', '<e>[/list]</e></LIST>'),
        new SimpleSubst('li', '<LI><s>[*]</s>', '</LI>'),
        new HeadingSubst(1, 200),
        new HeadingSubst(2, 160),
        new HeadingSubst(3, 130),
        new HeadingSubst(4, 100),
        new HeadingSubst(5, 85),
        new HeadingSubst(6, 50),
        new MiaQuoteSubst(),
        new MiaSpoilerSubst(),
        new SpanSubst(),
        new SameSubst('sup'),
        new SameSubst('sub'),
        new SameSubst('table'),
        new SimpleSubst('tr', "\n<br/><TR><s>[tr]</s>", "<e>[/tr]</e></TR>\n<br/>"),
        new SameSubst('th'),
        new SameSubst('td'),
        $videoFactory->getSubst('youtube.com/embed', 'YOUTUBE', '#youtube.com/embed/([a-zA-Z0-9_-]+)#'),
        $videoFactory->getSubst('youtube.com', 'YOUTUBE', '#youtube.com/watch?v=([a-zA-Z0-9_-]+)#'),
        $videoFactory->getSubst('vimeo', 'VIMEO', '#vimeo.com/([0-9]+)#'),
        $videoFactory->getSubst('twitch.tv', 'TWITCH', '#videos/[0-9]+#'),
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

