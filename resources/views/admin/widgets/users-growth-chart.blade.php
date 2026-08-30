{{--
    Expects: $labels (list<string>), $data (list<int>) -- same length,
    one point per month.

    The canvas only carries the raw series; initCharts() in admin.js turns
    it into a Chart.js line chart and reads the current --nk-primary theme
    token for the stroke colour, so this stays correct after a re-brand
    with zero changes here.
--}}
<x-admin::card :title="__('New users')">
    <div class="nk-chart">
        <canvas
            data-chart="{{ json_encode(['type' => 'line', 'labels' => $labels, 'datasets' => [['label' => __('New users'), 'data' => $data]]]) }}"
            role="img"
            aria-label="{{ __('New users per month, last 6 months') }}"
        ></canvas>
    </div>
</x-admin::card>
