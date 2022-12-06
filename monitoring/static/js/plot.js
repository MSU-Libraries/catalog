function plot(node, pt_x, pt_y, data_name, period) {
    var trace1 = {
        type: 'scatter',
        x: pt_x,
        y: pt_y
    };
    var data = [ trace1 ];
    var layout = { 
        title: data_name + ' for the last ' + period,
        font: { size: 16 },
        yaxis: {
            title: 'Minimum ' + data_name.replaceAll('_', ' ') + ' (%)'
        }
    };
    var config = { responsive: true };
    Plotly.newPlot('graph'+node, data, layout, config);
}
