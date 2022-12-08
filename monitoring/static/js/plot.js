function plot(variable, period, data) {
    var variable_with_spaces = variable.replaceAll('_', ' ')
    var ytitle = variable == 'apache_requests' ? 'Unfiltered apache requests' :
        'Minimum ' + variable_with_spaces + ' (%)';
    var title = variable_with_spaces.charAt(0).toUpperCase() + variable_with_spaces.slice(1) +
        ' for the last ' + period;
    var layout = { 
        title: title,
        font: { size: 16 },
        yaxis: {
            title: ytitle
        }
    };
    var config = { responsive: true };
    Plotly.newPlot('plot', data, layout, config);
}
