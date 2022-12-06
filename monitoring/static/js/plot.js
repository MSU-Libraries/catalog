function plot(node, pt_x, pt_y, data_name, period) {
    var trace1 = {
        type: 'scatter',
        x: pt_x,
        y: pt_y
    };
    var data = [ trace1 ];
    var ytitle = data_name == 'apache_requests' ? 'unfiltered apache requests' :
        'Minimum ' + data_name.replaceAll('_', ' ') + ' (%)';
    var layout = { 
        title: data_name.replaceAll('_', ' ') + ' for the last ' + period,
        font: { size: 16 },
        yaxis: {
            title: ytitle
        }
    };
    var config = { responsive: true };
    Plotly.newPlot('graph'+node, data, layout, config);
}
