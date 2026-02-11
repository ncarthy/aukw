import { Component, inject, Input, OnInit, SimpleChanges } from '@angular/core';
import { JsonPipe, NgClass } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import {
  NgbDateAdapter,
  NgbDateParserFormatter,
  NgbDatepickerModule,
  NgbActiveModal,
  NgbTooltipModule,
} from '@ng-bootstrap/ng-bootstrap';
import { environment } from '@environments/environment';
import {
  QBAccountListEntry,
  QBAttachment,
  ValueIdPair,
  ValueIdType,
} from '@app/_models';
import {
  AlertService,
  AuthenticationService,
  AuditLogService,
  QBAttachmentService,
  QBEntityService,
  QBPurchaseService,
  QBTransferService,
  TradeMatchService,
} from '@app/_services';
import { CustomDateParserFormatter, NgbUTCStringAdapter } from '@app/_helpers';
import { Observable, forkJoin, of, concatMap, tap } from 'rxjs';

@Component({
  selector: 'interco-trade',
  imports: [
    NgbDatepickerModule,
    ReactiveFormsModule,
    JsonPipe,
    NgbTooltipModule,
    NgClass,
  ],
  templateUrl: './interco-trade.component.html',
  styleUrl: './interco-trade.component.css',
  providers: [
    { provide: NgbDateAdapter, useClass: NgbUTCStringAdapter },
    { provide: NgbDateParserFormatter, useClass: CustomDateParserFormatter },
  ],
})
export class IntercoTradeComponent implements OnInit {
  // This is the trade that is already in QBO, for which we will create a matching trade in the other company
  @Input() existingTrade: QBAccountListEntry | null = null;
  @Input() enterprises: boolean = true; // When 'true' existingTrade is in Enterprises, in Charity otherwise

  form!: FormGroup;
  submitted = false;
  loading = false;
  attachments: QBAttachment[] = [];
  vendors: ValueIdPair[] = [];
  accounts: ValueIdType[] = [];
  customers: ValueIdPair[] = [];

  private realmid: string = environment.qboEnterprisesRealmID;
  private otherRealmid: string = environment.qboCharityRealmID;

  public modal: NgbActiveModal = inject(NgbActiveModal);

  private formBuilder = inject(FormBuilder);
  private attachmentService = inject(QBAttachmentService);
  private entityService = inject(QBEntityService);
  private alertService = inject(AlertService);
  private purchaseService = inject(QBPurchaseService);
  private transferService = inject(QBTransferService);
  private matchService = inject(TradeMatchService);
  private auditLogService = inject(AuditLogService);
  private authenticationService = inject(AuthenticationService);

  ngOnInit(): void {
    this.form = this.formBuilder.group({
      txnDate: [null, Validators.required],
      entity: [null, Validators.required],
      amount: [
        null,
        [
          Validators.required,
          Validators.pattern('^-?[0-9]\\d*(\\.\\d{1,2})?$'),
        ],
      ],
      IsVAT: [false],
      privateNote: [null],
      attachments: [null],
      account: [null, Validators.required],
      taxAmount: [null, Validators.required],
      description: [null],
      docnumber: [null],
    });

    if (this.enterprises) {
      this.realmid = environment.qboEnterprisesRealmID;
      this.otherRealmid = environment.qboCharityRealmID;
    } else {
      this.realmid = environment.qboCharityRealmID;
      this.otherRealmid = environment.qboEnterprisesRealmID;
    }

    this.getVendorsCustomersAccounts(this.otherRealmid);

    if (!this.existingTrade) return;

    this.matchService.match(this.realmid, this.existingTrade).subscribe({
      next: (response) => {
        // Check for non-empty response
        if (response && Object.keys(response).length) {
          this.f['entity'].setValue(response.name ? response.name.id : null);
          this.f['account'].setValue(response.account.id);
          this.f['amount'].setValue(response.amount);
          this.f['txnDate'].setValue(response.date);
          this.f['description'].setValue(response.description);
          this.f['IsVAT'].setValue(response.taxable);
          let vat = response.taxable ? this.vatCalculate() : 0;
          this.f['taxAmount'].setValue(vat);
          this.f['docnumber'].setValue(response.docnumber);

          if (response.memo) {
            this.f['privateNote'].setValue(response.memo);
          }
        } else {
          this.f['entity'].setValue(null);
          this.f['account'].setValue(null);
          this.f['amount'].setValue(this.existingTrade!.amount);
          this.f['txnDate'].setValue(this.existingTrade!.date);
          this.f['privateNote'].setValue(this.existingTrade!.memo);
          this.f['docnumber'].setValue(this.existingTrade!.docnumber);
          this.f['IsVAT'].setValue(false);
          this.f['taxAmount'].setValue(0);
          this.f['description'].setValue('');
        }
      },
      error: (error: any) => {
        this.alertService.error(error, { autoClose: false });
      },
      complete: () => {},
    });

    // Download attachemnts (if any)
    this.downloadAttachments(this.realmid, this.existingTrade);
  }

  /**
   * Download all the attachments (if any) for a QBO trade.
   * @param realmid The QBO id of the company file.
   * @param trade The currently selected trade that is already in QBO.
   */
  private downloadAttachments(realmid: string, trade: QBAccountListEntry) {
    this.loading = true;
    this.attachments = [];
    this.attachmentService
      .downloadAttachments(realmid, trade.type.value, trade.type.id)
      .subscribe({
        next: (response) => {
          this.attachments = response;
          this.f['attachments'].setValue(this.attachments.length);
        },
        error: (error: any) => {
          this.loading = false;
          this.attachments = [];
          this.alertService.error(error, { autoClose: false });
        },
        complete: () => (this.loading = false),
      });
  }

  /**
   * Store vendors, customers and accounts at module-level.
   * @param realmid The QBO id of the company file.
   */
  private getVendorsCustomersAccounts(realmid: string) {
    this.loading = true;

    var $obs = {
      accounts: this.entityService.getAllAccounts(realmid),
      customers: this.entityService.getAllCustomers(realmid),
      vendors: this.entityService.getAllVendors(realmid),
    };

    forkJoin($obs).subscribe({
      next: (x) => {
        var filteredAccounts = x.accounts.filter((x) => {
          return (
            x.type.includes('Expense', 0) || x.type == 'Cost of Goods Sold'
          );
        });
        this.accounts = filteredAccounts;
        this.customers = x.customers;
        this.vendors = x.vendors;
      },
      error: (error: any) => {
        this.loading = false;
        this.accounts = [];
        this.customers = [];
        this.vendors = [];
        this.alertService.error(error, { autoClose: false });
      },
      complete: () => (this.loading = false),
    });
  }

  /** Convenience getter for easy access to form fields */
  get f() {
    return this.form.controls;
  }

  onVatCheckboxClick() {
    this.f['IsVAT'].setValue(!this.f['IsVAT'].value);
    if (this.f['IsVAT'].value) {
      this.f['taxAmount'].setValue(this.vatCalculate());
    } else {
      // VAT is being turned off
      this.f['taxAmount'].setValue(0);
    }
  }

  vatCalculate() {
    return Math.round((this.f['amount'].value * 100) / 6) / 100;
  }

  onSubmit() {
    this.submitted = true;

    // reset alerts on submit
    this.alertService.clear();

    // stop here if form is invalid
    if (this.form.invalid) {
      return;
    }

    this.createPurchaseTrade();
  }

  createPurchaseTrade() {
    this.loading = true;
    var $obs: Observable<any>[] = [];
    forkJoin({
      purchase: this.purchaseService.create(this.otherRealmid, this.form.value),
      transfer: this.transferService.create(this.otherRealmid, {
        txnDate: this.form.value.txnDate,
        amount: this.form.value.amount,
        privateNote: this.form.value.privateNote,
      }),
    })
      .pipe(
        // Add entry to audit log
        tap((result) => {
          this.auditLogService.log(
            this.authenticationService.userValue,
            'INSERT',
            `Added expense with id=${result.purchase.id} to QuickBooks`,
            'Expense',
            result.purchase.id,
          );
        }),
        tap((result) => {
          this.auditLogService.log(
            this.authenticationService.userValue,
            'INSERT',
            `Added transfer with id=${result.transfer.id} to QuickBooks`,
            'Transfer',
            result.transfer.id,
          );
        }),
        concatMap((response) => {
          if (this.attachments && this.attachments.length) {
            this.attachments.forEach((attachment) => {
              $obs.push(
                this.attachmentService.uploadAttachments(
                  this.otherRealmid,
                  [
                    { value: response.purchase.id ?? 0, type: 'Purchase' },
                    { value: response.transfer.id ?? 0, type: 'Transfer' },
                  ],
                  [
                    {
                      FileName: attachment.FileName,
                      ContentType: attachment.ContentType,
                    },
                  ],
                ),
              );
            });
            return forkJoin($obs);
          } else {
            return of([response.purchase]);
          }
        }),
      )
      .subscribe({
        next: (x) => {
          this.alertService.success(
            `Created expense and associated transfer with attachment(s).`,
            { autoClose: true },
          );
        },
        error: (error: any) => {
          this.loading = false;
          this.alertService.error(error, { autoClose: false });
        },
        complete: () => {
          this.loading = false;
          this.modal.close();
        },
      });
    return;
  }
}
