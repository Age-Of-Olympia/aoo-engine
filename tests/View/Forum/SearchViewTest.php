<?php

namespace Tests\View\Forum;

use App\View\Forum\SearchView;
use PHPUnit\Framework\TestCase;

class SearchViewTest extends TestCase
{
    public function testAPostCannotBringItsOwnMarkupIntoTheResults(): void
    {
        $excerpt = SearchView::excerpt("<img src=x onerror=alert(1)> bonjour à tous\nligne deux", 'bonjour');

        $this->assertStringNotContainsString('<img', $excerpt);
        $this->assertStringContainsString('<font color="red"> bonjour </font>', $excerpt);
        $this->assertStringNotContainsString('ligne deux', $excerpt);
    }

    public function testTheSearchedWordIsEscapedToo(): void
    {
        $excerpt = SearchView::excerpt('un <b> mot', '<b>');

        $this->assertSame('un<font color="red"> &lt;b&gt; </font>mot', $excerpt);
    }
}
