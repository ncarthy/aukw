import { ValueStringIdPair } from './qb-account-list-entry';

/**
 * A QBO class is another name for a Project. It is a label that can be assigned to
 * transactions in order to gather income and expenditure under project headings.
 */
export class QBClass extends ValueStringIdPair {
  /** A shorter version of the name of the class */
  shortName: string;

  constructor(obj?: any) {
    super(obj);
    this.shortName = (obj && obj.shortName) || null;
  }
}
