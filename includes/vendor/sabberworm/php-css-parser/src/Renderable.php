<?php
 namespace Sabberworm\CSS; interface Renderable { public function __toString(); public function render($oOutputFormat); public function getLineNo(); } 