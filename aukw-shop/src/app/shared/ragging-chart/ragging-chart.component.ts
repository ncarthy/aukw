import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import Highcharts from 'highcharts/es-modules/masters/highcharts.src.js'; // From https://github.com/highcharts/highcharts/issues/14183
import { RaggingChartData } from '@app/_models';

@Component({
  selector: 'ragging-chart',
  standalone: true,
  imports: [],
  templateUrl: './ragging-chart.component.html',
  styleUrl: './ragging-chart.component.css',
})
export class RaggingChartComponent implements OnChanges {
  @Input() raggingChartData?: RaggingChartData;

  public options: Highcharts.Options = {
    title: {
      text: 'Value of All Items Recycled by Ragging',
    },
    subtitle: {
      text: 'By Fiscal Quarter',
    },
    yAxis: {
      title: {
        text: '£',
      },
    },

    tooltip: {
      xDateFormat: '%e %B %Y',
      shared: true,
    },

    xAxis: {
      type: 'datetime',
      accessibility: {
        rangeDescription: 'Historical ragging by quarter',
      },
      categories: [],
      labels: {
        formatter: function () {
          // format codes: https://api.highcharts.com/class-reference/Highcharts.Time#dateFormat
          return Highcharts.dateFormat('%Y-Q%q', this.value as number);
        },
      },
      tickInterval: 1000 * 60 * 60 * 24 * 30, // 30 days
    },

    series: [
      {
        name: 'Total Value Recycled',
        data: [],
        type: 'line',
        color: '#FF0000',
      },
    ],

    responsive: {
      rules: [
        {
          condition: {
            maxWidth: 400,
          },
          chartOptions: {
            legend: {
              layout: 'horizontal',
              align: 'center',
              verticalAlign: 'bottom',
            },
          },
        },
      ],
    },
  };

  ngOnChanges(changes: SimpleChanges) {
    if (changes['raggingChartData']) {
      /* The elaborate if statement below is to allow typescript to
       * detect the presence of the 'data' property.
       */
      if (this.options.series && this.raggingChartData) {
        let seriesOptionsType = this.options
          .series[0] as Highcharts.SeriesOptionsType;
        if (seriesOptionsType && seriesOptionsType.type === 'line') {
          seriesOptionsType.data = this.raggingChartData.total;
        }

        /**
         * Highcharts.dateFormats is a hook that allows you to define new date format codes.
         * In this case we are defining '%q' to give the quarter numer of the date.
         *
         * The Highcharts.dateFormats allows us to add new date format codes in the format
         * Record<string,TimeFormatCallbackFunction>
         * Where TimeFormatCallbackFunction is of the (timestamp:number) => string
         * It must return a string!
         */
        Highcharts.dateFormats = {
          q: function (timestamp: number): string {
            var date = new Date(timestamp),
              quarter = Math.floor(date.getUTCMonth() / 3) + 1;
            return quarter.toString();
          },
        };

        Highcharts.chart('ragging-chart', this.options);
      }
    }
  }
}
