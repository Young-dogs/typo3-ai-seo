import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import Icons from '@typo3/backend/icons.js';
import Modal from '@typo3/backend/modal.js';

class AiSeoStatus {
  constructor(config) {
    this.config = config;
    this.fields = [];
    this.rows = new Map();
  }

  async init() {
    const table = [...document.querySelectorAll('table')].find((t) => t.querySelector('tr[data-uid]'));
    if (!table) {
      return;
    }
    this.table = table;
    this.rows = new Map(
      [...table.querySelectorAll('tr[data-uid]')]
        .filter((tr) => parseInt(tr.dataset.uid, 10) > 0)
        .map((tr) => [parseInt(tr.dataset.uid, 10), tr]),
    );
    if (this.rows.size === 0) {
      return;
    }

    let data;
    try {
      const response = await new AjaxRequest(this.config.rowsUrl).post({
        id: this.config.pageId,
        lang: this.config.lang,
        uids: [...this.rows.keys()],
      });
      data = await response.resolve();
    } catch (e) {
      Notification.error(this.config.labels.errorTitle, this.config.labels.errorGeneric);
      return;
    }
    this.fields = data.fields;
    this.buildColumns();
    this.rows.forEach((tr, uid) => {
      this.updateCells(tr, data.rows[uid] ?? null);
      this.addButton(tr, uid);
    });
  }

  buildColumns() {
    const headerRow = [...this.table.querySelectorAll('tr')].find((tr) => !tr.hasAttribute('data-uid'));
    if (!headerRow) {
      return;
    }
    this.fields.forEach((field) => {
      const th = document.createElement('th');
      th.textContent = field.label;
      headerRow.append(th);
    });
  }

  updateCells(tr, values) {
    tr.querySelectorAll('td[data-ai-seo-field]').forEach((td) => td.remove());
    this.fields.forEach((field) => {
      const td = document.createElement('td');
      td.dataset.aiSeoField = field.name;
      td.style.minWidth = '12rem';
      td.style.maxWidth = '24rem';
      const value = values?.[field.name] ?? '';
      if (value === '') {
        td.classList.add('text-body-secondary');
        td.textContent = '–';
      } else {
        td.textContent = value;
        td.title = field.maxLength > 0 ? `${value.length}/${field.maxLength}` : String(value.length);
        if (field.maxLength > 0 && value.length > field.maxLength) {
          td.classList.add('text-danger');
        }
      }
      tr.append(td);
    });
  }

  addButton(tr, uid) {
    const group = tr.querySelector('.btn-group');
    if (!group || this.fields.length === 0) {
      return;
    }
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-default';
    button.title = this.config.labels.generate;
    Icons.getIcon('actions-wand-sparkles', Icons.sizes.small).then((icon) => {
      button.innerHTML = icon;
    });
    button.addEventListener('click', () => this.generate(tr, uid, button));
    group.append(button);
  }

  async generate(tr, uid, button) {
    if (button.disabled) {
      return;
    }
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = await Icons.getIcon('spinner-circle', Icons.sizes.small);
    try {
      const response = await new AjaxRequest(this.config.generateUrl).post({
        id: this.config.pageId,
        uid,
        lang: this.config.lang,
      });
      const result = await response.resolve();
      (result.warnings ?? []).forEach((warning) => Notification.warning(this.config.labels.warningTitle, warning));
      this.showPreview(tr, uid, button, result.generated, result.existing);
    } catch (error) {
      let message = this.config.labels.errorGeneric;
      try {
        const result = await error.response.json();
        message = result.message || message;
      } catch (e) {
        // keep the generic message
      }
      Notification.error(this.config.labels.errorTitle, message);
    } finally {
      button.disabled = false;
      button.innerHTML = originalHtml;
    }
  }

  showPreview(tr, uid, button, generated, existing) {
    const container = document.createElement('div');
    const table = document.createElement('table');
    table.className = 'table table-striped';
    const head = document.createElement('thead');
    const selectAllRow = document.createElement('tr');
    const selectAllCell = document.createElement('th');
    const selectAll = document.createElement('input');
    selectAll.type = 'checkbox';
    selectAll.className = 'form-check-input';
    selectAll.id = 'ai-seo-select-all';
    selectAll.style.appearance = 'auto';
    selectAll.style.webkitAppearance = 'auto';
    selectAll.style.width = '18px';
    selectAll.style.height = '18px';
    selectAll.style.margin = '0';
    selectAll.style.accentColor = 'var(--typo3-input-active-bg, #2179c7)';
    selectAll.setAttribute('aria-label', this.config.labels.selectAll);
    const selectAllLabel = document.createElement('label');
    selectAllLabel.className = 'ms-2';
    selectAllLabel.htmlFor = selectAll.id;
    selectAllLabel.textContent = this.config.labels.selectAll;
    selectAllCell.append(selectAll, selectAllLabel);

    const fieldHeader = document.createElement('th');
    const oldHeader = document.createElement('th');
    oldHeader.textContent = this.config.labels.oldValue;
    const newHeader = document.createElement('th');
    newHeader.textContent = this.config.labels.newValue;
    selectAllRow.append(selectAllCell, fieldHeader, oldHeader, newHeader);
    head.append(selectAllRow);
    table.append(head);

    const body = document.createElement('tbody');
    const checkboxes = new Map();
    this.fields.forEach((field) => {
      if (!Object.prototype.hasOwnProperty.call(generated, field.name)) {
        return;
      }
      const row = document.createElement('tr');
      const selectCell = document.createElement('td');
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'form-check-input';
      checkbox.style.appearance = 'auto';
      checkbox.style.webkitAppearance = 'auto';
      checkbox.style.width = '18px';
      checkbox.style.height = '18px';
      checkbox.style.margin = '0';
      checkbox.style.accentColor = 'var(--typo3-input-active-bg, #2179c7)';
      checkbox.checked = String(existing[field.name] ?? '').trim() === '';
      checkbox.dataset.fieldName = field.name;
      checkbox.setAttribute('aria-label', field.label);
      selectCell.append(checkbox);
      checkboxes.set(field.name, checkbox);
      checkbox.addEventListener('change', () => {
        const states = [...checkboxes.values()].map((item) => item.checked);
        selectAll.checked = states.every(Boolean);
        selectAll.indeterminate = states.some(Boolean) && !states.every(Boolean);
      });
      const label = document.createElement('th');
      label.textContent = field.label;
      const oldCell = document.createElement('td');
      oldCell.textContent = existing[field.name] || '–';
      oldCell.className = 'text-body-secondary';
      const newCell = document.createElement('td');
      newCell.textContent = generated[field.name];
      newCell.className = 'text-primary';
      row.append(selectCell, label, oldCell, newCell);
      body.append(row);
    });
    table.append(body);
    container.append(table);

    const updateSelectAll = () => {
      const states = [...checkboxes.values()].map((checkbox) => checkbox.checked);
      selectAll.checked = states.every(Boolean);
      selectAll.indeterminate = states.some(Boolean) && !states.every(Boolean);
    };
    updateSelectAll();
    selectAll.addEventListener('change', () => {
      selectAll.indeterminate = false;
      checkboxes.forEach((checkbox) => {
        checkbox.checked = selectAll.checked;
      });
    });

    const modal = Modal.advanced({
      title: this.config.labels.previewTitle,
      content: container,
      size: 'large',
      buttons: [
        {
          text: this.config.labels.cancel,
          btnClass: 'btn-default',
          active: true,
          trigger: () => modal.hideModal(),
        },
        {
          text: this.config.labels.apply,
          btnClass: 'btn-primary',
          icon: 'actions-document-save',
          trigger: () => {
            const selected = {};
            checkboxes.forEach((checkbox, fieldName) => {
              if (checkbox.checked) {
                selected[fieldName] = generated[fieldName];
              }
            });
            if (Object.keys(selected).length === 0) {
              modal.hideModal();
              return;
            }
            this.apply(tr, uid, selected).then(() => modal.hideModal());
          },
        },
      ],
    });
  }

  async apply(tr, uid, generated) {
    try {
      const response = await new AjaxRequest(this.config.applyUrl).post({
        id: this.config.pageId,
        uid,
        lang: this.config.lang,
        values: generated,
      });
      const result = await response.resolve();
      this.updateCells(tr, result.values);
      Notification.success(this.config.labels.successTitle, result.message);
    } catch (error) {
      let message = this.config.labels.errorGeneric;
      try {
        const result = await error.response.json();
        message = result.message || message;
      } catch (e) {
        // keep the generic message
      }
      Notification.error(this.config.labels.errorTitle, message);
    }
  }

  escapeHtml(value) {
    const element = document.createElement('span');
    element.textContent = value;
    return element.innerHTML;
  }
}

const configElement = document.getElementById('ai-seo-config');
if (configElement) {
  new AiSeoStatus(JSON.parse(configElement.dataset.config)).init();
}
