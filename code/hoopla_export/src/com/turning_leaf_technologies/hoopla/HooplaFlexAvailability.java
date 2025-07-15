package com.turning_leaf_technologies.hoopla;

public class HooplaFlexAvailability {
    public long id;
    public long hooplaId;
    public String rawResponse;
    public Integer holdsQueueSize;
    public Integer availableCopies;
    public Integer totalCopies;
    public String status;

    public HooplaFlexAvailability(long id, long hooplaId, String rawResponse, Integer holdsQueueSize,
    Integer availableCopies, Integer totalCopies, String status) {
        this.id = id;
        this.hooplaId = hooplaId;
        this.rawResponse = rawResponse;
        this.holdsQueueSize = holdsQueueSize != null ? holdsQueueSize : 0;
        this.availableCopies = availableCopies != null ? availableCopies : 0;
        this.totalCopies = totalCopies != null ? totalCopies : 0;
        this.status = status != null ? status : null;
    }

    public long getId() {
        return id;
    }

    public long getHooplaId() {
        return hooplaId;
    }

    public String getRawResponse() {
        return rawResponse;
    }

    public Integer getHoldsQueueSize() {
        return holdsQueueSize;
    }

    public Integer getAvailableCopies() {
        return availableCopies;
    }

    public Integer getTotalCopies() {
        return totalCopies;
    }

    public String getStatus() {
        return status;
    }
}
