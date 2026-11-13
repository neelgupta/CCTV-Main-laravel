<?php

// Registry of placeable fields for the letterhead format builder.
// 'type' => 'point' renders a draggable label only (x/y).
// 'type' => 'box' renders a draggable + resizable rectangle (x/y/width/height).
return [
    'quote_number' => ['label' => 'Invoice/Quote Number', 'type' => 'point'],
    'quote_date' => ['label' => 'Date', 'type' => 'point'],
    'client_name' => ['label' => 'Client Name & Address', 'type' => 'point'],
    'subject' => ['label' => 'Subject Line', 'type' => 'point'],
    'items_table' => ['label' => 'Items Table (incl. totals)', 'type' => 'box'],
    'terms_note' => ['label' => 'Terms / Notes', 'type' => 'box'],
    'signature_block' => ['label' => 'Signature', 'type' => 'point'],
];
