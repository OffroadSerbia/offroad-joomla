<?php

declare(strict_types=1);

namespace Offroad\Plugin\System\Offroadseo\Services;

/**
 * Service for handling HTML content injection at various positions
 */
class InjectionService extends AbstractService
{
  /** @var array<int,string> */ private array $headTopInjections = [];
  /** @var array<int,string> */ private array $headEndInjections = [];
  /** @var array<int,string> */ private array $bodyStartInjections = [];
  /** @var array<int,string> */ private array $bodyEndInjections = [];

    public function isEnabled(): bool
    {
        return (bool) $this->params->get('enable_custom_injections', 1);
    }

  /**
   * Add content to head top injection
   *
   * @param string $content
   */
    public function addHeadTop(string $content): void
    {
        if ($content !== '') {
            $this->headTopInjections[] = $content;
        }
    }

  /**
   * Add content to head end injection
   *
   * @param string $content
   */
    public function addHeadEnd(string $content): void
    {
        if ($content !== '') {
            $this->headEndInjections[] = $content;
        }
    }

  /**
   * Add content to body start injection
   *
   * @param string $content
   */
    public function addBodyStart(string $content): void
    {
        if ($content !== '') {
            $this->bodyStartInjections[] = $content;
        }
    }

  /**
   * Add content to body end injection
   *
   * @param string $content
   */
    public function addBodyEnd(string $content): void
    {
        if ($content !== '') {
            $this->bodyEndInjections[] = $content;
        }
    }

  /**
   * Apply all injections to the HTML body
   *
   * @param string $body        Original HTML body
   * @param bool   $wrapMarkers Whether to wrap injections with HTML comments
   * @return string Modified HTML body
   */
    public function applyInjections(string $body, bool $wrapMarkers = false): string
    {
        if (!$this->isEnabled()) {
            return $body;
        }

      // 1) Head TOP: before first <script> in <head>, or immediately after <head> if none
        if (!empty($this->headTopInjections)) {
            $body = $this->injectHeadTop($body, $wrapMarkers);
        }

      // 2) Head END: before </head>
        if (!empty($this->headEndInjections)) {
            $body = $this->injectHeadEnd($body, $wrapMarkers);
        }

      // 3) Body START: right after <body ...>
        if (!empty($this->bodyStartInjections)) {
            $body = $this->injectBodyStart($body, $wrapMarkers);
        }

      // 4) Body END: before </body>
        if (!empty($this->bodyEndInjections)) {
            $body = $this->injectBodyEnd($body, $wrapMarkers);
        }

        return $body;
    }

  /**
   * Process custom code from plugin parameters
   *
   * @param bool $wrapMarkers Whether to wrap with debug markers
   */
    public function processCustomCode(bool $wrapMarkers = false): void
    {
        if (!$this->isEnabled()) {
            return;
        }

      // Head end custom code
        $headEndCustom = (string) $this->params->get('head_end_custom_code', '');
        if ($headEndCustom !== '') {
            $content = $wrapMarkers ?
            ("<!-- OffroadSEO: Custom (head-end) start -->\n" . $headEndCustom . "\n<!-- OffroadSEO: Custom (head-end) end -->") :
            $headEndCustom;
            $this->addHeadEnd($content);
        }

      // Body start custom code
        $bodyStartCustom = (string) $this->params->get('body_start_custom_code', '');
        if ($bodyStartCustom !== '') {
            $content = $wrapMarkers ?
            ("<!-- OffroadSEO: Custom (body-start) start -->\n" . $bodyStartCustom . "\n<!-- OffroadSEO: Custom (body-start) end -->") :
            $bodyStartCustom;
            $this->addBodyStart($content);
        }

      // Body end custom code
        $bodyEndCustom = (string) $this->params->get('body_end_custom_code', '');
        if ($bodyEndCustom !== '') {
            $content = $wrapMarkers ?
            ("<!-- OffroadSEO: Custom (body-end) start -->\n" . $bodyEndCustom . "\n<!-- OffroadSEO: Custom (body-end) end -->") :
            $bodyEndCustom;
            $this->addBodyEnd($content);
        }
    }

  /**
   * Clear all injection buffers
   */
    public function clearAll(): void
    {
        $this->headTopInjections = [];
        $this->headEndInjections = [];
        $this->bodyStartInjections = [];
        $this->bodyEndInjections = [];
    }

  /**
   * Inject content at head top position
   *
   * @param string $body
   * @param bool   $wrapMarkers
   * @return string
   */
    private function injectHeadTop(string $body, bool $wrapMarkers): string
    {
        $content = "\n" . ($wrapMarkers ? "<!-- OffroadSEO: HEAD TOP start -->\n" : '') .
        implode("\n\n", $this->headTopInjections) .
        ($wrapMarkers ? "\n<!-- OffroadSEO: HEAD TOP end -->" : '') . "\n";

        if (preg_match('/<head\b[^>]*>/i', $body, $m, PREG_OFFSET_CAPTURE)) {
            $headOpenPos = $m[0][1];
            $headContentStart = $headOpenPos + strlen($m[0][0]);
            $headClosePos = stripos($body, '</head>', $headContentStart);

            if ($headClosePos !== false) {
                $headContent = substr($body, $headContentStart, $headClosePos - $headContentStart);
                $scriptPosInHead = stripos($headContent, '<script');

                if ($scriptPosInHead !== false) {
                  // Insert before first script
                    $insertPos = $headContentStart + $scriptPosInHead;
                    return substr($body, 0, $insertPos) . $content . substr($body, $insertPos);
                } else {
                  // Insert right after <head>
                    return substr($body, 0, $headContentStart) . $content . substr($body, $headContentStart);
                }
            }
        }

        return $body;
    }

  /**
   * Inject content at head end position
   *
   * @param string $body
   * @param bool   $wrapMarkers
   * @return string
   */
    private function injectHeadEnd(string $body, bool $wrapMarkers): string
    {
        $content = "\n" . ($wrapMarkers ? "<!-- OffroadSEO: HEAD END start -->\n" : '') .
        implode("\n\n", $this->headEndInjections) .
        ($wrapMarkers ? "\n<!-- OffroadSEO: HEAD END end -->" : '') . "\n";

        if (stripos($body, '</head>') !== false) {
            return preg_replace('/<\/head>/i', $content . '</head>', $body, 1);
        } else {
            return $content . $body;
        }
    }

  /**
   * Inject content at body start position
   *
   * @param string $body
   * @param bool   $wrapMarkers
   * @return string
   */
    private function injectBodyStart(string $body, bool $wrapMarkers): string
    {
        $content = "\n" . ($wrapMarkers ? "<!-- OffroadSEO: BODY START start -->\n" : '') .
        implode("\n\n", $this->bodyStartInjections) .
        ($wrapMarkers ? "\n<!-- OffroadSEO: BODY START end -->" : '') . "\n";

        if (preg_match('/<body\b[^>]*>/i', $body, $bm, PREG_OFFSET_CAPTURE)) {
            $openEnd = $bm[0][1] + strlen($bm[0][0]);
            return substr($body, 0, $openEnd) . $content . substr($body, $openEnd);
        } else {
            return $content . $body;
        }
    }

  /**
   * Inject content at body end position
   *
   * @param string $body
   * @param bool   $wrapMarkers
   * @return string
   */
    private function injectBodyEnd(string $body, bool $wrapMarkers): string
    {
        $content = "\n" . ($wrapMarkers ? "<!-- OffroadSEO: BODY END start -->\n" : '') .
        implode("\n\n", $this->bodyEndInjections) .
        ($wrapMarkers ? "\n<!-- OffroadSEO: BODY END end -->" : '') . "\n";

        if (stripos($body, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $content . '</body>', $body, 1);
        } else {
            return $body . $content;
        }
    }
}
