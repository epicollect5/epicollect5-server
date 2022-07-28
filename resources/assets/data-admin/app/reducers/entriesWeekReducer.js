import {
    FETCH_ENTRIES_STATS
} from 'config/actions';

const initialState = {
    overall: null,
    private: null,
    public: null,
    wasRejected: null,
    error:''
};

export default function entriesWeekReducer(state = initialState, action) {

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
            const entriesWeek = stats.entries.week.private + stats.entries.week.public;
            const branchEntriesWeek = stats.branch_entries.week.private + stats.branch_entries.week.public;
            const entriesWeekOverall = entriesWeek + branchEntriesWeek;
            const entriesWeekPublic = stats.entries.week.public + stats.branch_entries.week.public;
            const entriesWeekPrivate = stats.entries.week.private + stats.branch_entries.week.private;

            return {
                ...state,
                wasRejected: false,
                overall: entriesWeekOverall,
                private: entriesWeekPublic,
                public: entriesWeekPrivate
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
