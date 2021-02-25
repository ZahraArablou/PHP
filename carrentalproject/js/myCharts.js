var pieChartLabel = [];
var element = document.getElementsByClassName('make');
for (var i = 0, l = element.length; i < l; i++) {
    pieChartLabel[i] = element[i].value;
}
var pieChartData = [];
var element = document.getElementsByClassName('carCount');
for (var i = 0, l = element.length; i < l; i++) {
    pieChartData[i] = element[i].value;
}

var ctx2 = document.getElementById("pieChart");

var myPieChart = new Chart(ctx2, {
    type: 'pie',
    data: {
        labels: pieChartLabel,
        datasets: [{
            data: pieChartData,
            backgroundColor: ["#3e95cd", "#8e5ea2", "#3cba9f", "#e8c3b9", "#c45850"]
        }]
    }
});

var barChartData = [];
var element = document.getElementsByClassName('amount');
for (var i = 0, l = element.length; i < l; i++) {
    barChartData[i] = element[i].value;
}

var barChartLabel = [];
var element = document.getElementsByClassName('carId');
for (var i = 0, l = element.length; i < l; i++) {
    barChartLabel[i] = element[i].value;
}
var ctx1 = document.getElementById("barChart");
var myBarChart = new Chart(ctx1, {
    type: 'bar',
    data: {
        labels: barChartLabel,
        datasets: [{
            data: barChartData,
            backgroundColor: ["#3e95cd", "#8e5ea2", "#3cba9f", "#e8c3b9", "#c45850"]

        }]
    },
    options: {
        legend: { display: false },
        title: {
            display: false,
            text: ''
        }
    }
});