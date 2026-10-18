<?php
if (($argv[1] ?? '') !== '/home/william/Desktop/repos/seeded-board/kb-author-change-seed-33') throw new RuntimeException('Unexpected screenshot board');
require '/home/william/Desktop/repos/seed-forum/bin/seed-bootstrap.php';
$previous = (int) $config['kb_hierarchical_index'];
if (isset($argv[2])) $config->set('kb_hierarchical_index', (int) $argv[2]);
echo $previous;
