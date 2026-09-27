export type Metric = { funnel_id: string; variant: string; leads: string; paid: string };
export function totals(metrics: Metric[], id: number) {
    const rows = metrics.filter((row) => Number(row.funnel_id) === id);
    const leads = rows.reduce((sum, row) => sum + Number(row.leads), 0);
    const paid = rows.reduce((sum, row) => sum + Number(row.paid), 0);
    return { leads, paid, rate: leads ? Math.round((paid / leads) * 100) : 0 };
}
