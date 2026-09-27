import { totals } from './metrics';
test('conversion aggregates variants and isolates funnels', () => {
    expect(totals([{funnel_id:'1', variant:'A', leads:'3', paid:'1'}, {funnel_id:'1', variant:'B', leads:'2', paid:'1'}, {funnel_id:'2', variant:'A', leads:'10', paid:'10'}], 1)).toEqual({leads:5, paid:2, rate:40});
});
test('empty funnel has a zero rate', () => { expect(totals([], 1).rate).toBe(0); });
