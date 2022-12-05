function plot(pt_x, pt_y, data, period) {
    var trace1 = {
        type: 'scatter',
        x: pt_x,
        y: pt_y
    };
    var data = [ trace1 ];
    var layout = { 
        title: data + ' for the last ' + period,
        font: { size: 16 }
    };
    var config = { responsive: true };
    Plotly.newPlot('graph', data, layout, config);
}
