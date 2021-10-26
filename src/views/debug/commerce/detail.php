<?php
use yii\helpers\Html;

/** @var craft\commerce\debug\CommercePanel $panel */
?>
<h1>Commerce Info</h1>
<ul class="nav nav-tabs">
    <?php
    foreach ($panel->data['nav'] as $k => $item) {
        echo Html::tag(
            'li',
            Html::a($item, '#comdebug-tab-' . $k, [
                'class' => $k === 0 ? 'nav-link active' : 'nav-link',
                'data-toggle' => 'tab',
                'role' => 'tab',
                'aria-controls' => 'comdebug-tab-' . $k,
                'aria-selected' => $k === 0 ? 'true' : 'false'
            ]),
            [
                'class' => 'nav-item'
            ]
        );
    }
    ?>
</ul>
<div class="tab-content">
    <?php
    foreach ($panel->data['content'] as $k => $item) {
        echo Html::tag('div', $item, [
            'class' => $k === 0 ? 'tab-pane fade active show' : 'tab-pane fade',
            'id' => 'comdebug-tab-' . $k
        ]);
    }
    ?>
</div>