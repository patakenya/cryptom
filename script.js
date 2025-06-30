document.addEventListener('DOMContentLoaded', function () {
    // Toggle mobile menu
    const mobileMenuButton = document.getElementById('mobileMenuButton');
    const mobileMenu = document.getElementById('mobileMenu');
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function () {
            mobileMenu.classList.toggle('hidden');
            const icon = mobileMenuButton.querySelector('i');
            icon.classList.toggle('ri-menu-line');
            icon.classList.toggle('ri-close-line');
            mobileMenuButton.setAttribute('aria-expanded', mobileMenu.classList.contains('hidden') ? 'false' : 'true');
        });
    }

    // Toggle user dropdown
    const userMenuButton = document.getElementById('userMenuButton');
    const userMenu = document.getElementById('userMenu');
    if (userMenuButton && userMenu) {
        userMenuButton.addEventListener('click', function () {
            userMenu.classList.toggle('hidden');
            userMenuButton.setAttribute('aria-expanded', userMenu.classList.contains('hidden') ? 'false' : 'true');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (event) {
            if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
                userMenuButton.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Earnings Chart Initialization
    const chartDom = document.getElementById('earningsChart');
    if (chartDom) {
        const myChart = echarts.init(chartDom);
        
        const option = {
            animation: false,
            tooltip: {
                trigger: 'axis',
                backgroundColor: 'rgba(255, 255, 255, 0.9)',
                borderColor: '#e2e8f0',
                borderWidth: 1,
                textStyle: {
                    color: '#1f2937'
                }
            },
            grid: {
                left: '3%',
                right: '3%',
                bottom: '3%',
                top: '3%',
                containLabel: true
            },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: ['Jun 7', 'Jun 8', 'Jun 9', 'Jun 10', 'Jun 11', 'Jun 12', 'Jun 13'],
                axisLine: {
                    lineStyle: {
                        color: '#e2e8f0'
                    }
                },
                axisLabel: {
                    color: '#6b7280'
                }
            },
            yAxis: {
                type: 'value',
                axisLine: {
                    show: false
                },
                axisLabel: {
                    color: '#6b7280'
                },
                splitLine: {
                    lineStyle: {
                        color: '#e2e8f0'
                    }
                }
            },
            series: [
                {
                    name: 'Daily Earnings',
                    type: 'line',
                    smooth: true,
                    symbol: 'none',
                    lineStyle: {
                        width: 3,
                        color: 'rgba(87, 181, 231, 1)'
                    },
                    areaStyle: {
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            {
                                offset: 0,
                                color: 'rgba(87, 181, 231, 0.2)'
                            },
                            {
                                offset: 1,
                                color: 'rgba(87, 181, 231, 0.01)'
                            }
                        ])
                    },
                    data: [32.40, 38.70, 42.30, 47.80, 50.10, 52.20, 52.20]
                },
                {
                    name: 'Referral Earnings',
                    type: 'line',
                    smooth: true,
                    symbol: 'none',
                    lineStyle: {
                        width: 3,
                        color: 'rgba(141, 211, 199, 1)'
                    },
                    areaStyle: {
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            {
                                offset: 0,
                                color: 'rgba(141, 211, 199, 0.2)'
                            },
                            {
                                offset: 1,
                                color: 'rgba(141, 211, 199, 0.01)'
                            }
                        ])
                    },
                    data: [0, 0, 0, 70, 70, 70, 87.50]
                }
            ]
        };
        
        myChart.setOption(option);
        
        window.addEventListener('resize', function () {
            myChart.resize();
        });
    }

    // Countdown Timer
    const updateTimer = document.getElementById('updateTimer');
    if (updateTimer) {
        function updateCountdown() {
            let timeString = updateTimer.textContent;
            let [hours, minutes, seconds] = timeString.split(':').map(Number);
            
            seconds--;
            
            if (seconds < 0) {
                seconds = 59;
                minutes--;
            }
            
            if (minutes < 0) {
                minutes = 59;
                hours--;
            }
            
            if (hours < 0) {
                hours = 3;
                minutes = 42;
                seconds = 18;
            }
            
            updateTimer.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        setInterval(updateCountdown, 1000);
    }

    // Earnings Counter Animation
    const counters = document.querySelectorAll('.earnings-counter');
    counters.forEach(counter => {
        const value = counter.textContent;
        
        setInterval(() => {
            const currentValue = parseFloat(counter.textContent.replace(/[^0-9.-]+/g, ''));
            const fluctuation = (Math.random() * 0.02) - 0.01; // -0.01 to +0.01
            const newValue = currentValue + fluctuation;
            
            if (counter.textContent.includes('$')) {
                counter.textContent = '$' + newValue.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            } else {
                counter.textContent = newValue.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
        }, 3000);
    });

    // Copy Referral Link
    const copyLinkBtn = document.getElementById('copyLinkBtn');
    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', function () {
            const referralLink = copyLinkBtn.previousElementSibling;
            
            referralLink.select();
            referralLink.setSelectionRange(0, 99999);
            
            navigator.clipboard.writeText(referralLink.value);
            
            const originalIcon = copyLinkBtn.innerHTML;
            copyLinkBtn.innerHTML = '<i class="ri-check-line"></i>';
            
            setTimeout(() => {
                copyLinkBtn.innerHTML = originalIcon;
            }, 2000);
        });
    }

    // Two-Factor Authentication Toggle
    const twoFactorToggle = document.getElementById('twoFactorToggle');
    const twoFactorSetup = document.getElementById('twoFactorSetup');
    if (twoFactorToggle && twoFactorSetup) {
        twoFactorToggle.addEventListener('change', function () {
            if (this.checked) {
                twoFactorSetup.classList.remove('hidden');
            } else {
                twoFactorSetup.classList.add('hidden');
            }
        });
    }
});