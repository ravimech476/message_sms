    function loadHourlyTrends() {
        fetch('{{ route("api.dashboard.hourly-trends") }}')
            .then(response => response.json())
            .then(data => {
                hourlyChart.data.labels = data.map(item => item.hour);
                hourlyChart.data.datasets[0].data = data.map(item => item.sent);
                hourlyChart.data.datasets[1].data = data.map(item => item.delivered);
                hourlyChart.update();
            })
            .catch(error => {
                console.error('Error loading hourly trends:', error);
            });
    }

    function loadLiveStats() {
        fetch('{{ route("api.dashboard.live-stats") }}')
            .then(response => response.json())
            .then(data => {
                // Update hourly stats
                document.getElementById('hourSent').textContent = new Intl.NumberFormat().format(data.hourly.sent);
                document.getElementById('hourDelivered').textContent = new Intl.NumberFormat().format(data.hourly.delivered);
                document.getElementById('hourSpent').textContent = '£' + new Intl.NumberFormat().format(data.hourly.spent);
                
                // Update today stats
                document.getElementById('todaySent').textContent = new Intl.NumberFormat().format(data.today.sent);
                document.getElementById('todayDeliveryRate').textContent = data.today.delivery_rate + '%';
                
                // Update timestamp
                const timestamp = new Date(data.timestamp);
                document.getElementById('lastUpdated').textContent = timestamp.toLocaleTimeString();
            })
            .catch(error => {
                console.error('Error loading live stats:', error);
            });
    }