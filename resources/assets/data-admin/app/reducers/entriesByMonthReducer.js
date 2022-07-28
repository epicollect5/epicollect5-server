import {
    FETCH_ENTRIES_STATS
} from 'config/actions';

const initialState = {
    byMonth: null,
    wasRejected: null,
    error: ''
};

export default function entriesByMonthReducer(state = initialState, action) {

    switch (action.type) {
        case FETCH_ENTRIES_STATS + '_PENDING':
            {
                return {
                    ...state
                };
            }
        case FETCH_ENTRIES_STATS + '_FULFILLED':
            {
                const stats = action.payload.data.data;
                const entriesByMonth = stats.entries.by_month;
                const branchEntriesByMonth = stats.branch_entries.by_month;
                const overallByMonth = [];

                //combine entries and branch entries totals
                entriesByMonth.forEach((month) => {
                    const monthName = Object.keys(month)[0];
                    let monthTotal = Object.values(month)[0];

                    branchEntriesByMonth.forEach((branchMonth) => {

                        if (branchMonth[monthName]) {
                            monthTotal += branchMonth[monthName];
                        }
                    });

                    overallByMonth.push({ [monthName]: monthTotal });
                });

                return {
                    ...state,
                    wasRejected: false,
                    byMonth: overallByMonth
                };
            }
        case FETCH_ENTRIES_STATS + '_REJECTED':
            {
                return {
                    ...state,
                    wasRejected: true,
                    error: action.payload.message
                };
            }
        default:
            return state;
    }
}
