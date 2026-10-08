const ctx = document.getElementById('myChart');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Pending', 'Approved', 'Rejected'],
        datasets: [{
            label: 'Clearance Statistics',
            data: [12, 19, 3]
        }]
    }
});