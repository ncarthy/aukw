import { ProfitAndLossData, PnlReportLineItem } from './profit-and-loss-data';

export class InStoreSalesData extends ProfitAndLossData {
  ragging: PnlReportLineItem;
  donations: PnlReportLineItem;
  instorecustomersales: PnlReportLineItem;
  miscellaneousincome: PnlReportLineItem;

  constructor(obj?: any) {
    super(obj);
    this.ragging = (obj && obj.ragging) || null;
    this.donations = (obj && obj.donations) || null;
    this.instorecustomersales = (obj && obj.inStoreCustomerSales) || null;
    this.miscellaneousincome = (obj && obj.miscellaneousIncome) || null;
  }
}
