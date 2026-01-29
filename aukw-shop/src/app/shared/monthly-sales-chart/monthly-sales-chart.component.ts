import { Component, OnInit } from '@angular/core';
import Highcharts from 'highcharts/es-modules/masters/highcharts.src.js'; // From https://github.com/highcharts/highcharts/issues/14183
import { ReportService } from '@app/_services';
import { mergeMap } from 'rxjs';
import { MonthlySalesChartData } from '@app/_models';
import { environment } from '@environments/environment';

/* from https://www.highcharts.com/docs/advanced-chart-features/highcharts-typescript-declarations */
import 'highcharts/es-modules/masters/modules/accessibility.src.js';
import 'highcharts/es-modules/masters/modules/exporting.src.js';

@Component({
  selector: 'monthly-sales-chart',
  templateUrl: './monthly-sales-chart.component.html',
  styleUrls: ['./monthly-sales-chart.component.css'],
  standalone: true,
  imports: [],
})
/*
 * Create two bar charts displaying  monthly sales using Highcarts
 */
export class MonthlySalesChartComponent implements OnInit {
  public optionsSimpleBarChart: Highcharts.Options = {
    chart: {
      type: 'column',
    },
    title: {
      text: 'Monthly Sales',
    },
    subtitle: {
      text: 'Last 36 Months',
    },
    xAxis: {
      categories: [],
    },
    yAxis: {
      title: {
        useHTML: true,
        text: 'Sales in £',
      },
    },
    plotOptions: {
      column: {
        pointPadding: 0.2,
        borderWidth: 0,
      },
    },
    series: [
      {
        name: 'Sales less cash expenses',
        data: [],
        type: 'column',
      },
    ],
  };
  public optionsStackedBarChart: Highcharts.Options = {
    chart: {
      type: 'column',
    },
    title: {
      text: 'Average Daily Sales',
    },
    subtitle: {
      text: 'Last 36 Months',
    },
    xAxis: {
      categories: [],
    },
    yAxis: {
      title: {
        useHTML: true,
        text: 'Average Daily Sales in £',
      },
    },
    plotOptions: {
      column: {
        stacking: 'normal',
      },
    },
    series: [
      {
        name: 'Clothing',
        data: [],
        type: 'column',
      },
      {
        name: 'Brica',
        data: [],
        type: 'column',
      },
      {
        name: 'Books',
        data: [],
        type: 'column',
      },
      {
        name: 'Linens',
        data: [],
        type: 'column',
      },
    ],
  };

  constructor(private reportService: ReportService) {}

  ngOnInit(): void {
    var t = new Date();
    var year = t.getFullYear();
    var month = t.getMonth(); // The number of the month: January is 0, February is 1,... December is 11

    this.reportService
      .getMonthlySalesChartData(
        environment.HARROWROAD_SHOPID,
        year - 3,
        ++month,
      )
      .pipe(
        // Convert Observable<MonthlySalesChartData[]> to Observable<MonthlySalesChartData>
        mergeMap((salesData: MonthlySalesChartData[]) => salesData),
      )
      .subscribe({
        next: (value: MonthlySalesChartData) => {
          const date = new Date(value.year, value.month - 1, 1);
          const month = date.toLocaleString('en-GB', { month: 'short' });
          const label = month + '-' + String(value.year).substring(2);
          if (this.optionsSimpleBarChart.xAxis) {
            if (
              (this.optionsSimpleBarChart.xAxis as Highcharts.XAxisOptions)
                .categories
            ) {
              (
                this.optionsSimpleBarChart.xAxis as Highcharts.XAxisOptions
              ).categories?.push(label);
            }
          }
          if (this.optionsStackedBarChart.xAxis) {
            if (
              (this.optionsStackedBarChart.xAxis as Highcharts.XAxisOptions)
                .categories
            ) {
              (
                this.optionsStackedBarChart.xAxis as Highcharts.XAxisOptions
              ).categories?.push(label);
            }
          }

          /* The elaborate if statements below are to allow typescript to
           * detect the presence of the 'data' property.
           */
          if (
            this.optionsSimpleBarChart.series &&
            this.optionsSimpleBarChart.series[0]
          ) {
            let seriesOptionsType = this.optionsSimpleBarChart
              .series[0] as Highcharts.SeriesOptionsType;
            if (seriesOptionsType && seriesOptionsType.type === 'column') {
              seriesOptionsType.data = seriesOptionsType.data || [];
              seriesOptionsType.data?.push(value.sales);
            }
          }

          if (this.optionsStackedBarChart.series) {
            if (this.optionsStackedBarChart.series[0]) {
              let seriesOptionsType = this.optionsStackedBarChart
                .series[0] as Highcharts.SeriesOptionsType;
              if (seriesOptionsType && seriesOptionsType.type === 'column') {
                seriesOptionsType.data = seriesOptionsType.data || [];
                seriesOptionsType.data?.push(value.avg_clothing);
              }
            }
            if (this.optionsStackedBarChart.series[1]) {
              let seriesOptionsType = this.optionsStackedBarChart
                .series[1] as Highcharts.SeriesOptionsType;
              if (seriesOptionsType && seriesOptionsType.type === 'column') {
                seriesOptionsType.data = seriesOptionsType.data || [];
                seriesOptionsType.data?.push(value.avg_brica);
              }
            }
            if (this.optionsStackedBarChart.series[2]) {
              let seriesOptionsType = this.optionsStackedBarChart
                .series[2] as Highcharts.SeriesOptionsType;
              if (seriesOptionsType && seriesOptionsType.type === 'column') {
                seriesOptionsType.data = seriesOptionsType.data || [];
                seriesOptionsType.data?.push(value.avg_books);
              }
            }
            if (this.optionsStackedBarChart.series[3]) {
              let seriesOptionsType = this.optionsStackedBarChart
                .series[3] as Highcharts.SeriesOptionsType;
              if (seriesOptionsType && seriesOptionsType.type === 'column') {
                seriesOptionsType.data = seriesOptionsType.data || [];
                seriesOptionsType.data?.push(value.avg_linens);
              }
            }
          }
        },
        complete: () => {
          // Create two charts
          Highcharts.chart('monthly-sales-chart', this.optionsSimpleBarChart);
          Highcharts.chart(
            'monthly-dept-sales-chart',
            this.optionsStackedBarChart,
          );
        },
      });
  }
}
