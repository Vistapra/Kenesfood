(function($) {
  'use strict';
  $(function() {

    Chart.defaults.global.legend.labels.usePointStyle = true;
    
    if ($("#serviceSaleProgress").length) {
      var bar = new ProgressBar.Circle(serviceSaleProgress, {
        color: 'url(#gradient)',
        // This has to be the same size as the maximum width to
        // prevent clipping
        strokeWidth: 8,
        trailWidth: 8,
        easing: 'easeInOut',
        duration: 1400,
        text: {
          autoStyleContainer: false
        },
        from: { color: '#aaa', width: 6 },
        to: { color: '#57c7d4', width: 6 }
      });

      bar.animate(.65);  // Number from 0.0 to 1.0
      bar.path.style.strokeLinecap = 'round';
      let linearGradient = '<defs><linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="0%" gradientUnits="userSpaceOnUse"><stop offset="20%" stop-color="#da8cff"/><stop offset="50%" stop-color="#9a55ff"/></linearGradient></defs>';
      bar.svg.insertAdjacentHTML('afterBegin', linearGradient);
    }
    if ($("#productSaleProgress").length) {
      var bar = new ProgressBar.Circle(productSaleProgress, {
        color: 'url(#productGradient)',
        // This has to be the same size as the maximum width to
        // prevent clipping
        strokeWidth: 8,
        trailWidth: 8,
        easing: 'easeInOut',
        duration: 1400,
        text: {
          autoStyleContainer: false
        },
        from: { color: '#aaa', width: 6 },
        to: { color: '#57c7d4', width: 6 }
      });

      bar.animate(.6);  // Number from 0.0 to 1.0
      bar.path.style.strokeLinecap = 'round';
      let linearGradient = '<defs><linearGradient id="productGradient" x1="0%" y1="0%" x2="100%" y2="0%" gradientUnits="userSpaceOnUse"><stop offset="40%" stop-color="#36d7e8"/><stop offset="70%" stop-color="#b194fa"/></linearGradient></defs>';
      bar.svg.insertAdjacentHTML('afterBegin', linearGradient);
    }
    if ($("#points-chart").length) {
      var ctx = document.getElementById('points-chart').getContext("2d");

      var gradientStrokeViolet = ctx.createLinearGradient(0, 0, 0, 181);
      gradientStrokeViolet.addColorStop(0, 'rgba(218, 140, 255, 1)');
      gradientStrokeViolet.addColorStop(1, 'rgba(154, 85, 255, 1)');

      var myChart = new Chart(ctx, {
          type: 'bar',
          data: {
              labels: [1, 2, 3, 4, 5, 6, 7, 8],
              datasets: [
                {
                  label: "North Zone",
                  borderColor: gradientStrokeViolet,
                  backgroundColor: gradientStrokeViolet,
                  hoverBackgroundColor: gradientStrokeViolet,
                  pointRadius: 0,
                  fill: false,
                  borderWidth: 1,
                  fill: 'origin',
                  data: [20, 40, 15, 35, 25, 50, 30, 20]
                },
                {
                  label: "South Zone",
                  borderColor: '#e9eaee',
                  backgroundColor: '#e9eaee',
                  hoverBackgroundColor: '#e9eaee',
                  pointRadius: 0,
                  fill: false,
                  borderWidth: 1,
                  fill: 'origin',
                  data: [40, 30, 20, 10, 50, 15, 35, 20]
                }
            ]
          },
          options: {
              legend: {
                  display: false
              },
              scales: {
                  yAxes: [{
                      ticks: {
                          display: false,
                          min: 0,
                          stepSize: 10
                      },
                      gridLines: {
                        drawBorder: false,
                        display: false
                      }
                  }],
                  xAxes: [{
                      gridLines: {
                        display:false,
                        drawBorder: false,
                        color: 'rgba(0,0,0,1)',
                        zeroLineColor: '#eeeeee'
                      },
                      ticks: {
                          padding: 20,
                          fontColor: "#9c9fa6",
                          autoSkip: true,
                      },
                      barPercentage: 0.7
                  }]
                }
              },
              elements: {
                point: {
                  radius: 0
                }
              }
            })
    }
    if ($("#events-chart").length) {
      var ctx = document.getElementById('events-chart').getContext("2d");

      var gradientStrokeBlue = ctx.createLinearGradient(0, 0, 0, 181);
      gradientStrokeBlue.addColorStop(0, 'rgba(54, 215, 232, 1)');
      gradientStrokeBlue.addColorStop(1, 'rgba(177, 148, 250, 1)');

      var myChart = new Chart(ctx, {
          type: 'bar',
          data: {
              labels: [1, 2, 3, 4, 5, 6, 7, 8],
              datasets: [
                {
                  label: "Domestic",
                  borderColor: gradientStrokeBlue,
                  backgroundColor: gradientStrokeBlue,
                  hoverBackgroundColor: gradientStrokeBlue,
                  pointRadius: 0,
                  fill: false,
                  borderWidth: 1,
                  fill: 'origin',
                  data: [20, 40, 15, 35, 25, 50, 30, 20]
                },
                {
                  label: "International",
                  borderColor: '#e9eaee',
                  backgroundColor: '#e9eaee',
                  hoverBackgroundColor: '#e9eaee',
                  pointRadius: 0,
                  fill: false,
                  borderWidth: 1,
                  fill: 'origin',
                  data: [40, 30, 20, 10, 50, 15, 35, 20]
                }
            ]
          },
          options: {
              legend: {
                  display: false
              },
              scales: {
                  yAxes: [{
                      ticks: {
                          display: false,
                          min: 0,
                          stepSize: 10
                      },
                      gridLines: {
                        drawBorder: false,
                        display: false
                      }
                  }],
                  xAxes: [{
                      gridLines: {
                        display:false,
                        drawBorder: false,
                        color: 'rgba(0,0,0,1)',
                        zeroLineColor: '#eeeeee'
                      },
                      ticks: {
                          padding: 20,
                          fontColor: "#9c9fa6",
                          autoSkip: true,
                      },
                      barPercentage: 0.7
                  }]
                }
              },
              elements: {
                point: {
                  radius: 0
                }
              }
            })
    }
    if ($("#visit-sale-chart").length) {
      Chart.defaults.global.legend.labels.usePointStyle = true;
      var ctx = document.getElementById('visit-sale-chart').getContext("2d");

      var gradientStrokeViolet = ctx.createLinearGradient(0, 0, 0, 181);
      gradientStrokeViolet.addColorStop(0, 'rgba(218, 140, 255, 1)');
      gradientStrokeViolet.addColorStop(1, 'rgba(154, 85, 255, 1)');
      var gradientLegendViolet = 'linear-gradient(to right, rgba(218, 140, 255, 1), rgba(154, 85, 255, 1))';
      
      var gradientStrokeBlue = ctx.createLinearGradient(0, 0, 0, 360);
      gradientStrokeBlue.addColorStop(0, 'rgba(54, 215, 232, 1)');
      gradientStrokeBlue.addColorStop(1, 'rgba(177, 148, 250, 1)');
      var gradientLegendBlue = 'linear-gradient(to right, rgba(54, 215, 232, 1), rgba(177, 148, 250, 1))';

      var gradientStrokeRed = ctx.createLinearGradient(0, 0, 0, 300);
      gradientStrokeRed.addColorStop(0, 'rgba(255, 191, 150, 1)');
      gradientStrokeRed.addColorStop(1, 'rgba(254, 112, 150, 1)');
      var gradientLegendRed = 'linear-gradient(to right, rgba(255, 191, 150, 1), rgba(254, 112, 150, 1))';

      var myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG'],
            datasets: [
              {
                label: "CHN",
                borderColor: gradientStrokeViolet,
                backgroundColor: gradientStrokeViolet,
                hoverBackgroundColor: gradientStrokeViolet,
                legendColor: gradientLegendViolet,
                pointRadius: 0,
                fill: false,
                borderWidth: 1,
                fill: 'origin',
                data: [20, 40, 15, 35, 25, 50, 30, 20]
              },
              {
                label: "USA",
                borderColor: gradientStrokeRed,
                backgroundColor: gradientStrokeRed,
                hoverBackgroundColor: gradientStrokeRed,
                legendColor: gradientLegendRed,
                pointRadius: 0,
                fill: false,
                borderWidth: 1,
                fill: 'origin',
                data: [40, 30, 20, 10, 50, 15, 35, 40]
              },
              {
                label: "UK",
                borderColor: gradientStrokeBlue,
                backgroundColor: gradientStrokeBlue,
                hoverBackgroundColor: gradientStrokeBlue,
                legendColor: gradientLegendBlue,
                pointRadius: 0,
                fill: false,
                borderWidth: 1,
                fill: 'origin',
                data: [70, 10, 30, 40, 25, 50, 15, 30]
              }
          ]
        },
        options: {
          responsive: true,
          legend: false,
          legendCallback: function(chart) {
            var text = []; 
            text.push('<ul>'); 
            for (var i = 0; i < chart.data.datasets.length; i++) { 
                text.push('<li><span class="legend-dots" style="background:' + 
                           chart.data.datasets[i].legendColor + 
                           '"></span>'); 
                if (chart.data.datasets[i].label) { 
                    text.push(chart.data.datasets[i].label); 
                } 
                text.push('</li>'); 
            } 
            text.push('</ul>'); 
            return text.join('');
          },
          scales: {
              yAxes: [{
                  ticks: {
                      display: false,
                      min: 0,
                      stepSize: 20,
                      max: 80
                  },
                  gridLines: {
                    drawBorder: false,
                    color: 'rgba(235,237,242,1)',
                    zeroLineColor: 'rgba(235,237,242,1)'
                  }
              }],
              xAxes: [{
                  gridLines: {
                    display:false,
                    drawBorder: false,
                    color: 'rgba(0,0,0,1)',
                    zeroLineColor: 'rgba(235,237,242,1)'
                  },
                  ticks: {
                      padding: 20,
                      fontColor: "#9c9fa6",
                      autoSkip: true,
                  },
                  categoryPercentage: 0.5,
                  barPercentage: 0.5
              }]
            }
          },
          elements: {
            point: {
              radius: 0
            }
          }
      })
      $("#visit-sale-chart-legend").html(myChart.generateLegend());
    }
    if ($("#visit-sale-chart-dark").length) {
      Chart.defaults.global.legend.labels.usePointStyle = true;
      var ctx = document.getElementById('visit-sale-chart-dark').getContext("2d");

      var gradientStrokeViolet = ctx.createLinearGradient(0, 0, 0, 181);
      gradientStrokeViolet.addColorStop(0, 'rgba(218, 140, 255, 1)');
      gradientStrokeViolet.addColorStop(1, 'rgba(154, 85, 255, 1)');
      var gradientLegendViolet = 'linear-gradient(to right, rgba(218, 140, 255, 1), rgba(154, 85, 255, 1))';
      
      var gradientStrokeBlue = ctx.createLinearGradient(0, 0, 0, 360);
      gradientStrokeBlue.addColorStop(0, 'rgba(54, 215, 232, 1)');
      gradientStrokeBlue.addColorStop(1, 'rgba(177, 148, 250, 1)');
      var gradientLegendBlue = 'linear-gradient(to right, rgba(54, 215, 232, 1), rgba(177, 148, 250, 1))';

      var gradientStrokeRed = ctx.createLinearGradient(0, 0, 0, 300);
      gradientStrokeRed.addColorStop(0, 'rgba(255, 191, 150, 1)');
      gradientStrokeRed.addColorStop(1, 'rgba(254, 112, 150, 1)');
      var gradientLegendRed = 'linear-gradient(to right, rgba(255, 191, 150, 1), rgba(254, 112, 150, 1))';

      var myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG'],
            datasets: [
              {
                label: "CHN",
                borderColor: gradientStrokeViolet,
                backgroundColor: gradientStrokeViolet,
                hoverBackgroundColor: gradientStrokeViolet,
                legendColor: gradientLegendViolet,
                pointRadius: 0,
                fill: false,
                borderWidth: 1,
                fill: 'origin',
                data: [20, 40, 15, 35, 25, 50, 30, 20]
              },
              {
                label: "USA",
                borderColor: gradientStrokeRed,
                backgroundColor: gradientStrokeRed,
                hoverBackgroundColor: gradientStrokeRed,
                legendColor: gradientLegendRed,
                pointRadius: 0,
                fill: false,
                borderWidth: 1,
                fill: 'origin',
                data: [40, 30, 20, 10, 50, 15, 35, 40]
              },
              {
                label: "UK",
                borderColor: gradientStrokeBlue,
                backgroundColor: gradientStrokeBlue,
                hoverBackgroundColor: gradientStrokeBlue,
                legendColor: gradientLegendBlue,
                pointRadius: 0,
                fill: false,
                borderWidth: 1,
                fill: 'origin',
                data: [70, 10, 30, 40, 25, 50, 15, 30]
              }
          ]
        },
        options: {
          responsive: true,
          legend: false,
          legendCallback: function(chart) {
            var text = []; 
            text.push('<ul>'); 
            for (var i = 0; i < chart.data.datasets.length; i++) { 
                text.push('<li><span class="legend-dots" style="background:' + 
                           chart.data.datasets[i].legendColor + 
                           '"></span>'); 
                if (chart.data.datasets[i].label) { 
                    text.push(chart.data.datasets[i].label); 
                } 
                text.push('</li>'); 
            } 
            text.push('</ul>'); 
            return text.join('');
          },
          scales: {
              yAxes: [{
                  ticks: {
                      display: false,
                      min: 0,
                      stepSize: 20,
                      max: 80
                  },
                  gridLines: {
                    drawBorder: false,
                    color: '#322f2f',
                    zeroLineColor: '#322f2f'
                  }
              }],
              xAxes: [{
                  gridLines: {
                    display:false,
                    drawBorder: false,
                    color: 'rgba(0,0,0,1)',
                    zeroLineColor: 'rgba(235,237,242,1)'
                  },
                  ticks: {
                      padding: 20,
                      fontColor: "#9c9fa6",
                      autoSkip: true,
                  },
                  categoryPercentage: 0.5,
                  barPercentage: 0.5
              }]
            }
          },
          elements: {
            point: {
              radius: 0
            }
          }
      })
      $("#visit-sale-chart-legend-dark").html(myChart.generateLegend());
    }
    if ($("#traffic-chart").length) {
      var gradientStrokeBlue = ctx.createLinearGradient(0, 0, 0, 181);
      gradientStrokeBlue.addColorStop(0, 'rgba(54, 215, 232, 1)');
      gradientStrokeBlue.addColorStop(1, 'rgba(177, 148, 250, 1)');
      var gradientLegendBlue = 'linear-gradient(to right, rgba(54, 215, 232, 1), rgba(177, 148, 250, 1))';

      var gradientStrokeRed = ctx.createLinearGradient(0, 0, 0, 50);
      gradientStrokeRed.addColorStop(0, 'rgba(255, 191, 150, 1)');
      gradientStrokeRed.addColorStop(1, 'rgba(254, 112, 150, 1)');
      var gradientLegendRed = 'linear-gradient(to right, rgba(255, 191, 150, 1), rgba(254, 112, 150, 1))';

      var gradientStrokeGreen = ctx.createLinearGradient(0, 0, 0, 300);
      gradientStrokeGreen.addColorStop(0, 'rgba(6, 185, 157, 1)');
      gradientStrokeGreen.addColorStop(1, 'rgba(132, 217, 210, 1)');
      var gradientLegendGreen = 'linear-gradient(to right, rgba(6, 185, 157, 1), rgba(132, 217, 210, 1))';      

      var trafficChartData = {
        datasets: [{
          data: [30, 30, 40],
          backgroundColor: [
            gradientStrokeBlue,
            gradientStrokeGreen,
            gradientStrokeRed
          ],
          hoverBackgroundColor: [
            gradientStrokeBlue,
            gradientStrokeGreen,
            gradientStrokeRed
          ],
          borderColor: [
            gradientStrokeBlue,
            gradientStrokeGreen,
            gradientStrokeRed
          ],
          legendColor: [
            gradientLegendBlue,
            gradientLegendGreen,
            gradientLegendRed
          ]
        }],
    
        // These labels appear in the legend and in the tooltips when hovering different arcs
        labels: [
          'Search Engines',
          'Direct Click',
          'Bookmarks Click',
        ]
      };
      var trafficChartOptions = {
        responsive: true,
        animation: {
          animateScale: true,
          animateRotate: true
        },
        legend: false,
        legendCallback: function(chart) {
          var text = []; 
          text.push('<ul>'); 
          for (var i = 0; i < trafficChartData.datasets[0].data.length; i++) { 
              text.push('<li><span class="legend-dots" style="background:' + 
              trafficChartData.datasets[0].legendColor[i] + 
                          '"></span>'); 
              if (trafficChartData.labels[i]) { 
                  text.push(trafficChartData.labels[i]); 
              }
              text.push('<span class="float-right">'+trafficChartData.datasets[0].data[i]+"%"+'</span>')
              text.push('</li>'); 
          } 
          text.push('</ul>'); 
          return text.join('');
        }
      };
      var trafficChartCanvas = $("#traffic-chart").get(0).getContext("2d");
      var trafficChart = new Chart(trafficChartCanvas, {
        type: 'doughnut',
        data: trafficChartData,
        options: trafficChartOptions
      });
      $("#traffic-chart-legend").html(trafficChart.generateLegend());      
    }
    if ($("#inline-datepicker").length) {
      $('#inline-datepicker').datepicker({
        enableOnReadonly: true,
        todayHighlight: true,
      });
    }
  });
})(jQuery);

// JavaScript untuk custom dashboard

(function($) {
	'use strict';
	$(function() {
	  Chart.defaults.global.legend.labels.usePointStyle = true;
  
	  // Fungsi untuk format angka dengan pemisah ribuan
	  function formatNumber(num) {
		return num.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.')
	  }
  
	  // Fungsi untuk menghasilkan array tanggal 7 hari terakhir
	  function getLastSevenDays() {
		var result = [];
		for (var i = 6; i >= 0; i--) {
		  var d = new Date();
		  d.setDate(d.getDate() - i);
		  result.push(d.getDate() + '/' + (d.getMonth() + 1));
		}
		return result;
	  }
  
	  // Setup for visitor chart
	  if ($("#visitor-chart").length) {
		var ctx = document.getElementById('visitor-chart').getContext('2d');
		var gradientStrokeViolet = ctx.createLinearGradient(0, 0, 0, 181);
		gradientStrokeViolet.addColorStop(0, 'rgba(218, 140, 255, 1)');
		gradientStrokeViolet.addColorStop(1, 'rgba(154, 85, 255, 1)');
		
		// Ambil data pengunjung hari ini dari hidden input - diisi dari PHP/Controller
		var visitorToday = parseInt($("#visitor-today-value").val() || 0);
		
		// Simulasi data pengunjung 7 hari terakhir - nanti bisa diganti dengan data real dari AJAX
		var visitorData = [45, 38, 62, 78, 56, 70, visitorToday];
		
		var visitorChart = new Chart(ctx, {
		  type: 'line',
		  data: {
			labels: getLastSevenDays(),
			datasets: [{
			  label: 'Jumlah Pengunjung',
			  data: visitorData,
			  backgroundColor: 'rgba(154, 85, 255, 0.2)',
			  borderColor: gradientStrokeViolet,
			  borderWidth: 3,
			  pointBackgroundColor: gradientStrokeViolet,
			  pointBorderColor: '#fff',
			  pointBorderWidth: 2,
			  pointRadius: 4,
			  fill: true
			}]
		  },
		  options: {
			responsive: true,
			maintainAspectRatio: false,
			legend: {
			  display: false,
			},
			tooltips: {
			  mode: 'index',
			  intersect: false,
			  callbacks: {
				label: function(tooltipItem, data) {
				  return tooltipItem.value + ' pengunjung';
				}
			  }
			},
			hover: {
			  mode: 'nearest',
			  intersect: true
			},
			scales: {
			  xAxes: [{
				gridLines: {
				  display: false,
				  drawBorder: false,
				},
				ticks: {
				  fontColor: "#9c9fa6",
				}
			  }],
			  yAxes: [{
				ticks: {
				  beginAtZero: true,
				  fontColor: "#9c9fa6",
				  stepSize: 20,
				  callback: function(value) {
					return value;
				  }
				},
				gridLines: {
				  color: "rgba(235,237,242,1)",
				  zeroLineColor: "rgba(235,237,242,1)",
				  drawBorder: false,
				}
			  }]
			}
		  }
		});
	  }
  
	  // Setup product distribution chart
	  if ($("#product-distribution-chart").length) {
		var ctxDonut = document.getElementById('product-distribution-chart').getContext('2d');
		
		var gradientBakery = ctxDonut.createLinearGradient(0, 0, 0, 181);
		gradientBakery.addColorStop(0, 'rgba(255, 191, 150, 1)');
		gradientBakery.addColorStop(1, 'rgba(254, 112, 150, 1)');
		var gradientLegendBakery = 'linear-gradient(to right, rgba(255, 191, 150, 1), rgba(254, 112, 150, 1))';
		
		var gradientResto = ctxDonut.createLinearGradient(0, 0, 0, 50);
		gradientResto.addColorStop(0, 'rgba(54, 215, 232, 1)');
		gradientResto.addColorStop(1, 'rgba(177, 148, 250, 1)');
		var gradientLegendResto = 'linear-gradient(to right, rgba(54, 215, 232, 1), rgba(177, 148, 250, 1))';
		
		var gradientKopitiam = ctxDonut.createLinearGradient(0, 0, 0, 300);
		gradientKopitiam.addColorStop(0, 'rgba(6, 185, 157, 1)');
		gradientKopitiam.addColorStop(1, 'rgba(132, 217, 210, 1)');
		var gradientLegendKopitiam = 'linear-gradient(to right, rgba(6, 185, 157, 1), rgba(132, 217, 210, 1))';
		
		// Ambil data produk dari hidden input - diisi dari PHP/Controller
		var bakeryCount = parseInt($("#bakery-count").val() || 0);
		var restoCount = parseInt($("#resto-count").val() || 0);
		var kopitiamCount = parseInt($("#kopitiam-count").val() || 0);
		
		var productDistributionData = {
		  datasets: [{
			data: [bakeryCount, restoCount, kopitiamCount],
			backgroundColor: [
			  gradientBakery,
			  gradientResto,
			  gradientKopitiam
			],
			borderColor: [
			  gradientBakery,
			  gradientResto,
			  gradientKopitiam
			],
			legendColor: [
			  gradientLegendBakery,
			  gradientLegendResto,
			  gradientLegendKopitiam
			]
		  }],
		  labels: [
			'Bakery',
			'Resto',
			'Kopitiam'
		  ]
		};
		
		var productChart = new Chart(ctxDonut, {
		  type: 'doughnut',
		  data: productDistributionData,
		  options: {
			responsive: true,
			maintainAspectRatio: false,
			cutoutPercentage: 70,
			legend: {
			  display: false
			},
			tooltips: {
			  callbacks: {
				label: function(tooltipItem, data) {
				  var dataset = data.datasets[tooltipItem.datasetIndex];
				  var total = dataset.data.reduce(function(previousValue, currentValue) {
					return previousValue + currentValue;
				  });
				  var currentValue = dataset.data[tooltipItem.index];
				  var percentage = Math.floor(((currentValue/total) * 100) + 0.5);
				  return data.labels[tooltipItem.index] + ': ' + currentValue + ' (' + percentage + "%)";
				}
			  }
			},
			animation: {
			  animateScale: true,
			  animateRotate: true
			}
		  }
		});
  
		// Generate custom legend
		if ($("#product-distribution-legend").length) {
		  var legendCallback = function(chart) {
			var text = [];
			text.push('<ul class="legend-list">');
			for (var i = 0; i < chart.data.datasets[0].data.length; i++) {
			  var total = chart.data.datasets[0].data.reduce(function(a, b) { return a + b; }, 0);
			  var percentage = Math.floor((chart.data.datasets[0].data[i] / total * 100) + 0.5);
			  
			  text.push('<li class="mb-3">');
			  text.push('<span class="legend-dots" style="background:' + 
						 chart.data.datasets[0].legendColor[i] + 
						'"></span>');
			  text.push(chart.data.labels[i] + ': ' + chart.data.datasets[0].data[i] + ' (' + percentage + '%)');
			  text.push('</li>');
			}
			text.push('</ul>');
			return text.join('');
		  };
		  
		  $("#product-distribution-legend").html(legendCallback(productChart));
		}
	  }
  
	  // Setup untuk refresh otomatis
	  $(".btn-refresh-data").on("click", function() {
		var $this = $(this);
		$this.addClass('disabled');
		$this.html('<i class="mdi mdi-refresh mdi-spin"></i> Memuat...');
		
		// Simulasi loading
		setTimeout(function() {
		  $this.removeClass('disabled');
		  $this.html('<i class="mdi mdi-refresh"></i> Refresh Data');
		  
		  // Disini bisa tambahkan AJAX call untuk refresh data
		  // $.ajax({
		  //   url: 'get_dashboard_data',
		  //   success: function(response) {
		  //     // Update chart dan data lainnya
		  //   }
		  // });
		  
		  // Tampilkan notifikasi sukses
		  Swal.fire({
			icon: 'success',
			title: 'Berhasil',
			text: 'Data dashboard telah diperbarui!',
			timer: 1500,
			showConfirmButton: false
		  });
		}, 1000);
	  });
  
	  // Setup untuk toggle detail produk
	  $(".toggle-product-details").on("click", function() {
		var target = $(this).data('target');
		$(target).slideToggle();
		
		// Toggle icon
		if ($(this).find('i').hasClass('mdi-chevron-down')) {
		  $(this).find('i').removeClass('mdi-chevron-down').addClass('mdi-chevron-up');
		} else {
		  $(this).find('i').removeClass('mdi-chevron-up').addClass('mdi-chevron-down');
		}
	  });
  
	  // Setup untuk notifikasi
	  $(".btn-mark-read").on("click", function() {
		var $item = $(this).closest('li');
		$item.fadeOut(300, function() {
		  $item.remove();
		  
		  // Jika tidak ada notifikasi lagi, tampilkan pesan kosong
		  if ($('.todo-list li').length === 0) {
			$('.todo-list').html('<li class="text-center py-4"><div>Tidak ada notifikasi terbaru</div></li>');
		  }
		});
		
		// Disini bisa tambahkan AJAX call untuk mark as read
		// var notifId = $(this).data('notif-id');
		// $.ajax({
		//   url: 'mark_notification_read',
		//   data: { id: notifId },
		//   method: 'POST'
		// });
	  });
  
	  // Init tooltips
	  $('[data-toggle="tooltip"]').tooltip();
  
	  // Animasi untuk kartu statistik
	  $('.card').each(function(index) {
		var $this = $(this);
		setTimeout(function() {
		  $this.addClass('fade-in-up animated');
		}, index * 100);
	  });
	  
	  // Setup untuk tabel yang sortable
	  if ($(".sortable-table").length) {
		$(".sortable-table").DataTable({
		  "ordering": true,
		  "info": false,
		  "searching": false,
		  "lengthChange": false,
		  "pageLength": 5,
		  "language": {
			"paginate": {
			  "previous": "<i class='mdi mdi-chevron-left'></i>",
			  "next": "<i class='mdi mdi-chevron-right'></i>"
			}
		  }
		});
	  }
	});
  })(jQuery);
