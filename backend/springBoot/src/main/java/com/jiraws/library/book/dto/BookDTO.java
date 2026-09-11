package com.jiraws.library.book.dto;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NonNull;

public class BookDTO {
    @Data
    @AllArgsConstructor
    @Builder
    public static  class PostInput{

        @NotNull @NotBlank
        String bookName;
        @NotNull
        Integer bookPage;
    }
}
