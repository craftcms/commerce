/**
 * The detail of the window events `OrderIndexViewModel::actions()` swaps the
 * order actions that need a settings step for. The bulk actions bar adds the
 * selection; `url` and `body` are the request the action would have made.
 */
export interface OrderActionEventDetail {
  url: string;
  body: Record<string, unknown>;
  elementType: string;
  elementIds: Array<string | number>;
  trigger?: Element;
}

type RequestFailure = {
  response?: {data?: unknown};
  message?: string;
};

export function showMessage(type: 'success' | 'error', message: string): void {
  window.dispatchEvent(
    new CustomEvent('craft-message', {detail: {type, message}})
  );
}

async function failureMessage(failure: unknown): Promise<string | null> {
  const data = (failure as RequestFailure).response?.data;
  const payload =
    data instanceof Blob ? await data.text().then(parseJson) : data;

  return (
    (payload as {message?: string} | null)?.message ??
    (failure as RequestFailure).message ??
    null
  );
}

function parseJson(text: string): unknown {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

function requestData(
  detail: OrderActionEventDetail,
  settings: Record<string, unknown>
): Record<string, unknown> {
  return {
    ...detail.body,
    ...settings,
    elementType: detail.elementType,
    elementIds: detail.elementIds,
  };
}

/**
 * Performs the order action with the settings picked for it, reporting the
 * outcome. Resolves to whether it succeeded.
 */
export async function performOrderAction(
  detail: OrderActionEventDetail,
  settings: Record<string, unknown>
): Promise<boolean> {
  try {
    const {data} = await window.Craft.sendActionRequest<{message?: string}>(
      'POST',
      detail.url,
      {data: requestData(detail, settings)}
    );

    if (data.message) {
      showMessage('success', data.message);
    }

    return true;
  } catch (failure) {
    const message = await failureMessage(failure);

    if (message) {
      showMessage('error', message);
    }

    return false;
  }
}

/**
 * Performs a download order action and saves the file it responds with.
 */
export async function downloadOrderAction(
  detail: OrderActionEventDetail,
  settings: Record<string, unknown>
): Promise<boolean> {
  try {
    const response = await window.Craft.sendActionRequest<Blob>(
      'POST',
      detail.url,
      {data: requestData(detail, settings), responseType: 'blob'}
    );

    saveBlob(
      response.data,
      filenameFrom(response.headers['content-disposition']) ?? 'download'
    );

    return true;
  } catch (failure) {
    const message = await failureMessage(failure);

    if (message) {
      showMessage('error', message);
    }

    return false;
  }
}

function filenameFrom(disposition: string | undefined): string | null {
  if (!disposition) {
    return null;
  }

  const encoded = /filename\*=UTF-8''([^;]+)/i.exec(disposition)?.[1];

  if (encoded) {
    return decodeURIComponent(encoded);
  }

  return /filename="?([^";]+)"?/i.exec(disposition)?.[1] ?? null;
}

function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.append(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

/** Mirrors the `OrderIndexViewModel::*_EVENT` constants. */
export const UPDATE_ORDER_STATUS_EVENT = 'commerce:update-order-status';
export const COPY_LOAD_CART_URL_EVENT = 'commerce:copy-load-cart-url';
export const DOWNLOAD_ORDER_PDF_EVENT = 'commerce:download-order-pdf';

/**
 * Shows the selected cart's load-cart URL in the CP's copy text prompt.
 */
export async function promptLoadCartUrl(
  detail: OrderActionEventDetail,
  label: string
): Promise<void> {
  try {
    const {data} = await window.Craft.sendActionRequest<{url?: string}>(
      'GET',
      'commerce/orders/get-load-cart-url',
      {params: {id: detail.elementIds[0]}}
    );

    if (data.url) {
      window.dispatchEvent(
        new CustomEvent('craft:copy-text-prompt', {
          detail: {label, value: data.url},
        })
      );
    }
  } catch (failure) {
    const message = await failureMessage(failure);

    if (message) {
      showMessage('error', message);
    }
  }
}
